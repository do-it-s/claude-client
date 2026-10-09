<?php

namespace DoITs\ClaudeClient\Vision;

use DoITs\ClaudeClient\ClaudeMessages;
use DoITs\ClaudeClient\Exceptions\ClaudeClientException;
use DoITs\ClaudeClient\Exceptions\TransportException;

/**
 * Sends one image plus a prompt to Claude and returns the structured result
 * the model filled into the record tool.
 */
class ClaudeVisionClient
{
    public const TOOL_NAME = 'record_result';

    private const SUPPORTED_MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly ClaudeMessages $messages) {}

    /**
     * @param  string  $image  The raw image bytes (JPEG, PNG, GIF or WebP)
     * @param  array<string, mixed>  $inputSchema  JSON Schema of the tool input
     * @param  string  $feature  Which feature's API key and log to use
     * @return array{input: array<string, mixed>, usage: array<string, mixed>, model: string}
     *
     * @throws ClaudeClientException
     */
    public function read(string $image, string $prompt, array $inputSchema, string $feature, string $model, int $maxTokens, int $timeoutSeconds): array
    {
        // Fail before anything is sent.
        if (blank($this->messages->apiKeyFor($feature))) {
            throw new ClaudeClientException('ANTHROPIC_API_KEY is not configured.');
        }

        $size = @getimagesizefromstring($image);

        if ($size === false || ! in_array($size['mime'], self::SUPPORTED_MEDIA_TYPES, true)) {
            throw new ClaudeClientException('The stored file is not a supported image (JPEG, PNG, GIF or WebP).');
        }

        try {
            $response = $this->messages->send($feature, 'vision-extraction', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'tools' => [[
                    'name' => self::TOOL_NAME,
                    'description' => 'Record the result read from the image.',
                    'input_schema' => $inputSchema,
                ]],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $size['mime'],
                                'data' => base64_encode($image),
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $this->text($prompt, $size[0], $size[1]),
                        ],
                    ],
                ]],
            ], $timeoutSeconds);
        } catch (TransportException $e) {
            throw new ClaudeClientException("Request to the Claude API failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new ClaudeClientException("The Claude API returned status {$response->status()}: {$response->errorMessage()}");
        }

        if ($response->stopReason() === 'max_tokens') {
            throw new ClaudeClientException('The Claude response was cut off at the token limit.');
        }

        $input = $response->toolInput(self::TOOL_NAME);

        if ($input === null) {
            throw new ClaudeClientException('The Claude response did not contain a result.');
        }

        return [
            'input' => $input,
            'usage' => $response->usage(),
            'model' => $response->model() ?? $model,
        ];
    }

    private function text(string $prompt, int $width, int $height): string
    {
        return "{$prompt}\n\nThe attached image is {$width}x{$height} pixels. Return the result with the ".self::TOOL_NAME.' tool.';
    }
}
