<?php

namespace DoITs\ClaudeClient;

use Illuminate\Support\Arr;

/**
 * What the Messages API answered. Callers depend on this, never on the HTTP
 * client's own response type.
 */
class MessagesResponse
{
    /**
     * @param  array<string, mixed>|null  $json  The decoded body, null when it was not a JSON object.
     */
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly ?array $json,
        private readonly ?string $requestId = null,
    ) {}

    public function status(): int
    {
        return $this->status;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * The decoded body, or one value of it by dot-notation key.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json;
        }

        return Arr::get($this->json ?? [], $key, $default);
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function model(): ?string
    {
        return $this->stringOrNull($this->json('model'));
    }

    public function stopReason(): ?string
    {
        return $this->stringOrNull($this->json('stop_reason'));
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        $usage = $this->json('usage');

        return is_array($usage) ? $usage : [];
    }

    /**
     * The response's text blocks, concatenated. Null when it has none.
     */
    public function text(): ?string
    {
        $texts = [];

        foreach ($this->contentBlocks() as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $texts[] = $block['text'];
            }
        }

        return $texts === [] ? null : implode('', $texts);
    }

    /**
     * The input the model filled into the named tool, null when it did not call it.
     *
     * @return array<string, mixed>|null
     */
    public function toolInput(string $name): ?array
    {
        foreach ($this->contentBlocks() as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $name) {
                $input = $block['input'] ?? [];

                return is_array($input) ? $input : [];
            }
        }

        return null;
    }

    /**
     * The API's own error message, or the raw body when it sent none.
     */
    public function errorMessage(): string
    {
        $message = $this->json('error.message');

        return is_string($message) ? $message : $this->body;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentBlocks(): array
    {
        $content = $this->json('content');

        return is_array($content) ? array_values(array_filter($content, is_array(...))) : [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
