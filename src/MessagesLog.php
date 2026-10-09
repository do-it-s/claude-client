<?php

namespace DoITs\ClaudeClient;

use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Throwable;

/**
 * One JSONL line per call in storage/logs/anthropic-{feature}.log: the
 * request and response in full, one ever-growing file. The API key is never
 * recorded.
 */
class MessagesLog
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function record(string $feature, string $purpose, array $body, float $startedAt, ?MessagesResponse $response = null, ?string $error = null): void
    {
        if (config('claude-client.log_disabled')) {
            return;
        }

        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path("logs/anthropic-{$feature}.log"),
                'level' => 'info',
                'formatter' => JsonFormatter::class,
            ])->info('messages', [
                'purpose' => $purpose,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'status' => $response?->status(),
                'request_id' => $response?->requestId(),
                'request' => $this->withoutImageData($body),
                'response' => $response === null ? null : ($response->json() ?? $response->body()),
                'error' => $error,
            ]);
        } catch (Throwable) {
            // Logging is for analysis only; never fail the call over it.
        }
    }

    /**
     * Base64 image payloads run to megabytes per call; keep their size, not their bytes.
     */
    private function withoutImageData(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (($value['type'] ?? null) === 'base64' && is_string($value['data'] ?? null)) {
            $value['data'] = '[omitted: '.strlen($value['data']).' base64 chars]';

            return $value;
        }

        return array_map($this->withoutImageData(...), $value);
    }
}
