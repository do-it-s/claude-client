<?php

namespace DoITs\ClaudeClient\Contracts;

use DoITs\ClaudeClient\Exceptions\TransportException;
use DoITs\ClaudeClient\MessagesResponse;

/**
 * Carries one Messages API request. The only implementation today talks HTTP
 * through Laravel's client; swapping in an SDK-based one stays inside this
 * package.
 */
interface MessagesTransport
{
    /**
     * @param  array<string, mixed>  $body
     *
     * @throws TransportException When the request itself fails.
     */
    public function send(string $apiKey, array $body, int $timeoutSeconds): MessagesResponse;
}
