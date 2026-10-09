<?php

use DoITs\ClaudeClient\ClaudeMessages;
use DoITs\ClaudeClient\Exceptions\ApiKeyMissingException;
use DoITs\ClaudeClient\Exceptions\TransportException;
use DoITs\ClaudeClient\MessagesResponse;
use DoITs\ClaudeClient\Vision\ClaudeVisionClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'claude-client.api_key' => 'shared-key',
        'claude-client.api_keys' => [],
    ]);
});

it('uses the feature key when set and falls back to the shared key otherwise', function () {
    config(['claude-client.api_keys.lyrics' => 'lyrics-key']);

    $messages = app(ClaudeMessages::class);

    expect($messages->apiKeyFor('lyrics'))->toBe('lyrics-key')
        ->and($messages->apiKeyFor('rjcc'))->toBe('shared-key');

    config(['claude-client.api_key' => null]);

    expect($messages->apiKeyFor('rjcc'))->toBeNull();
});

it('sends with the feature key to the Messages endpoint', function () {
    config(['claude-client.api_keys.rjcc' => 'rjcc-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => []])]);

    app(ClaudeMessages::class)->send('rjcc', 'test', ['model' => 'm'], 5);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->header('x-api-key') === ['rjcc-key']
        && $request->header('anthropic-version') === ['2023-06-01']
        && $request['model'] === 'm');
});

it('does not call the API when no key is configured', function () {
    config(['claude-client.api_key' => null]);
    Http::fake();

    expect(fn () => app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5))
        ->toThrow(ApiKeyMissingException::class, 'ANTHROPIC_API_KEY');

    Http::assertNothingSent();
});

it('returns a MessagesResponse that hides the HTTP client', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => 'Hello, '], ['type' => 'text', 'text' => 'world']],
        'usage' => ['input_tokens' => 3, 'output_tokens' => 4],
    ], 200, ['request-id' => 'req_9'])]);

    $response = app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);

    expect($response)->toBeInstanceOf(MessagesResponse::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->failed())->toBeFalse()
        ->and($response->status())->toBe(200)
        ->and($response->text())->toBe('Hello, world')
        ->and($response->model())->toBe('claude-sonnet-5-5')
        ->and($response->stopReason())->toBe('end_turn')
        ->and($response->usage())->toBe(['input_tokens' => 3, 'output_tokens' => 4])
        ->and($response->requestId())->toBe('req_9')
        ->and($response->json('content.0.type'))->toBe('text')
        ->and($response->toolInput('record_result'))->toBeNull();
});

it('exposes the API error message of a failed response', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

    $response = app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);

    expect($response->failed())->toBeTrue()
        ->and($response->errorMessage())->toBe('overloaded');
});

it('falls back to the raw body when a failed response is not JSON', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('Bad Gateway', 502)]);

    $response = app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);

    expect($response->json())->toBeNull()
        ->and($response->errorMessage())->toBe('Bad Gateway');
});

it('logs the full request and response to the feature channel without the api key', function () {
    $records = captureAnthropicLog('lyrics');
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi']]], 200, ['request-id' => 'req_123'])]);

    app(ClaudeMessages::class)->send('lyrics', 'song-name-normalizer', ['model' => 'm', 'messages' => [['role' => 'user', 'content' => 'full prompt']]], 5);

    expect($records)->toHaveCount(1);

    ['message' => $message, 'context' => $context] = $records[0];

    expect($message)->toBe('messages')
        ->and($context['purpose'])->toBe('song-name-normalizer')
        ->and($context['status'])->toBe(200)
        ->and($context['request_id'])->toBe('req_123')
        ->and($context['request']['messages'][0]['content'])->toBe('full prompt')
        ->and($context['response']['content'][0]['text'])->toBe('hi')
        ->and(json_encode($context))->not->toContain('shared-key');
});

it('does not log when logging is disabled', function () {
    config(['claude-client.log_disabled' => true]);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => []])]);
    Log::shouldReceive('build')->never();

    app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);
});

it('logs a failed request and rethrows it as a TransportException', function () {
    $records = captureAnthropicLog('lyrics');
    Http::fake(['api.anthropic.com/*' => fn () => throw new ConnectionException('timed out')]);

    try {
        app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);
        $this->fail('Expected a TransportException.');
    } catch (TransportException $e) {
        expect($e->getMessage())->toContain('timed out')
            ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }

    expect($records[0]['context']['status'])->toBeNull()
        ->and($records[0]['context']['error'])->toContain('timed out');
});

it('does not fail the call when writing the log fails', function () {
    config(['claude-client.log_disabled' => false]);
    Log::shouldReceive('build')->andThrow(new RuntimeException('disk full'));
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => []])]);

    $response = app(ClaudeMessages::class)->send('lyrics', 'p', ['model' => 'm'], 5);

    expect($response->successful())->toBeTrue();
});

it('replaces base64 image data in the log with its size', function () {
    $records = captureAnthropicLog('rjcc');
    Http::fake(['api.anthropic.com/*' => Http::response(claudeToolResponse(['flights' => []]))]);

    app(ClaudeVisionClient::class)->read(tinyPng(), 'Read the chart.', ['type' => 'object'], 'rjcc', 'm', 1000, 5);

    $context = $records[0]['context'];
    $source = $context['request']['messages'][0]['content'][0]['source'];

    expect($context['purpose'])->toBe('vision-extraction')
        ->and($source['data'])->toStartWith('[omitted: ')
        ->and($source['media_type'])->toBe('image/png')
        ->and($context['request']['messages'][0]['content'][1]['text'])->toContain('Read the chart.');
});
