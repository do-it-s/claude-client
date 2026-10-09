<?php

namespace DoITs\ClaudeClient\Support;

/**
 * Despite being told to answer with ONLY JSON, models sometimes wrap it in a
 * markdown code fence or a sentence. Taking the outermost brackets is more
 * robust than stripping every fence variant.
 */
class JsonExtractor
{
    /**
     * The outermost {...} of the text, decoded. Null when there is none or it is not valid JSON.
     *
     * @return array<string, mixed>|null
     */
    public static function object(mixed $text): ?array
    {
        return self::between($text, '{', '}');
    }

    /**
     * The outermost [...] of the text, decoded. Null when there is none or it is not valid JSON.
     *
     * @return list<mixed>|null
     */
    public static function list(mixed $text): ?array
    {
        return self::between($text, '[', ']');
    }

    /**
     * @return array<mixed>|null
     */
    private static function between(mixed $text, string $open, string $close): ?array
    {
        if (! is_string($text)) {
            return null;
        }

        $start = strpos($text, $open);
        $end = strrpos($text, $close);

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
