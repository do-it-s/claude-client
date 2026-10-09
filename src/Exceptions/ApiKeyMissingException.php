<?php

namespace DoITs\ClaudeClient\Exceptions;

class ApiKeyMissingException extends ClaudeClientException
{
    public static function forFeature(string $feature): self
    {
        return new self("ANTHROPIC_API_KEY is not configured (feature \"{$feature}\" has no key of its own in claude-client.api_keys either).");
    }
}
