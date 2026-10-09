<?php

namespace DoITs\ClaudeClient;

use DoITs\ClaudeClient\Contracts\MessagesTransport;
use DoITs\ClaudeClient\Exceptions\ApiKeyMissingException;
use DoITs\ClaudeClient\Exceptions\TransportException;

/**
 * Single entry point to the Anthropic Messages API. Each feature has its own
 * API key (so Claude Platform's cost view breaks usage down per feature) and
 * its own JSONL log of every call (see MessagesLog).
 *
 * The request body is passed through as given: model, max_tokens, temperature
 * and the timeout are the caller's to decide.
 */
class ClaudeMessages
{
    public function __construct(
        private readonly MessagesTransport $transport,
        private readonly MessagesLog $log,
    ) {}

    /**
     * The feature's own key, or the shared one until the feature gets its own.
     */
    public function apiKeyFor(string $feature): ?string
    {
        $key = config("claude-client.api_keys.{$feature}") ?: config('claude-client.api_key');

        return filled($key) ? $key : null;
    }

    /**
     * @param  string  $purpose  Which call within the feature this is (e.g. "song-name-normalizer"), recorded in the log.
     * @param  array<string, mixed>  $body
     *
     * @throws ApiKeyMissingException When neither the feature nor the shared key is set.
     * @throws TransportException When the request itself fails (after logging it).
     */
    public function send(string $feature, string $purpose, array $body, int $timeoutSeconds): MessagesResponse
    {
        $apiKey = $this->apiKeyFor($feature) ?? throw ApiKeyMissingException::forFeature($feature);

        $startedAt = microtime(true);

        try {
            $response = $this->transport->send($apiKey, $body, $timeoutSeconds);
        } catch (TransportException $e) {
            $this->log->record($feature, $purpose, $body, $startedAt, error: $e->getMessage());

            throw $e;
        }

        $this->log->record($feature, $purpose, $body, $startedAt, $response);

        return $response;
    }
}
