<?php

use DoITs\ClaudeClient\Tests\TestCase;
use Illuminate\Support\Facades\Log;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Captures what is written to a feature's Anthropic log.
 *
 * @return ArrayObject<int, array{message: string, context: array<string, mixed>}>
 */
function captureAnthropicLog(string $feature): ArrayObject
{
    config(['claude-client.log_disabled' => false]);

    $records = new ArrayObject;
    $logger = Mockery::mock();
    $logger->shouldReceive('info')->andReturnUsing(function (string $message, array $context) use ($records) {
        $records[] = ['message' => $message, 'context' => $context];
    });
    Log::shouldReceive('build')
        ->withArgs(fn (array $config) => $config['path'] === storage_path("logs/anthropic-{$feature}.log"))
        ->andReturn($logger);

    return $records;
}

/**
 * A 1x1 PNG.
 */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
}

/**
 * A Messages API response in which the model filled the record tool.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function claudeToolResponse(array $input, array $overrides = []): array
{
    return [
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'tool_use',
        'content' => [
            ['type' => 'thinking', 'thinking' => '...'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'record_result', 'input' => $input],
        ],
        'usage' => ['input_tokens' => 6031, 'output_tokens' => 19229],
        ...$overrides,
    ];
}
