<?php

use DoITs\ClaudeClient\Exceptions\ClaudeClientException;
use DoITs\ClaudeClient\Vision\ClaudeVisionClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'claude-client.api_key' => 'test-key',
        'claude-client.api_keys' => [],
        'claude-client.log_disabled' => true,
    ]);
});

function readImage(?string $image = null, string $model = 'claude-sonnet-5-5'): array
{
    return app(ClaudeVisionClient::class)->read($image ?? tinyPng(), 'p', [], 'rjcc', $model, 24000, 540);
}

it('sends the image, prompt and schema and returns the tool result', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudeToolResponse(['flights' => [['spot' => '0']]]))]);

    $result = app(ClaudeVisionClient::class)->read(tinyPng(), 'Read the chart.', ['type' => 'object'], 'rjcc', 'claude-sonnet-5-5', 24000, 540);

    expect($result['input'])->toBe(['flights' => [['spot' => '0']]])
        ->and($result['usage'])->toBe(['input_tokens' => 6031, 'output_tokens' => 19229])
        ->and($result['model'])->toBe('claude-sonnet-5-5');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $content = $body['messages'][0]['content'];

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key') === ['test-key']
            && $body['model'] === 'claude-sonnet-5-5'
            && $body['max_tokens'] === 24000
            && $body['tools'][0]['name'] === 'record_result'
            && $body['tools'][0]['input_schema'] === ['type' => 'object']
            && ! array_key_exists('tool_choice', $body)
            && $content[0]['source']['media_type'] === 'image/png'
            && $content[0]['source']['data'] === base64_encode(tinyPng())
            && str_contains($content[1]['text'], 'Read the chart.')
            && str_contains($content[1]['text'], '1x1 pixels');
    });
});

it('reports the model the API answered with, or the requested one when it says none', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeToolResponse([], ['model' => 'claude-opus-5-5']))
        ->push(claudeToolResponse([], ['model' => null]))]);

    expect(readImage(model: 'claude-opus-5-5')['model'])->toBe('claude-opus-5-5')
        ->and(readImage(model: 'requested-model')['model'])->toBe('requested-model');
});

it('fails with the API error message on an HTTP error', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'image too large']], 400)]);

    readImage();
})->throws(ClaudeClientException::class, 'status 400: image too large');

it('fails when the response was cut off at the token limit', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudeToolResponse([], ['stop_reason' => 'max_tokens']))]);

    readImage();
})->throws(ClaudeClientException::class, 'cut off');

it('fails when the model answered without using the tool', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => 'I cannot read this.']],
    ])]);

    readImage();
})->throws(ClaudeClientException::class, 'did not contain a result');

it('fails without calling the API when no key is configured', function () {
    config(['claude-client.api_key' => null]);
    Http::fake();

    expect(fn () => readImage())->toThrow(ClaudeClientException::class, 'ANTHROPIC_API_KEY');

    Http::assertNothingSent();
});

it('fails without calling the API when the file is not an image', function () {
    Http::fake();

    expect(fn () => readImage('not an image'))->toThrow(ClaudeClientException::class, 'not a supported image');

    Http::assertNothingSent();
});

it('wraps a connection failure in a ClaudeClientException', function () {
    Http::fake(['api.anthropic.com/*' => fn () => throw new ConnectionException('timed out')]);

    readImage();
})->throws(ClaudeClientException::class, 'Request to the Claude API failed: timed out');
