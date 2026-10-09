<?php

namespace DoITs\ClaudeClient\Transport;

use DoITs\ClaudeClient\Contracts\MessagesTransport;
use DoITs\ClaudeClient\Exceptions\TransportException;
use DoITs\ClaudeClient\MessagesResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpTransport implements MessagesTransport
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function send(string $apiKey, array $body, int $timeoutSeconds): MessagesResponse
    {
        try {
            $response = Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                ])
                ->post(self::ENDPOINT, $body);
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), previous: $e);
        }

        return new MessagesResponse(
            $response->status(),
            $response->body(),
            $response->json(),
            $response->header('request-id') ?: null,
        );
    }
}
