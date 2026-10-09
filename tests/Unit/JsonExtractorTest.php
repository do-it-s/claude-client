<?php

use DoITs\ClaudeClient\Support\JsonExtractor;

it('extracts an object from bare, fenced or chatty text', function (string $text) {
    expect(JsonExtractor::object($text))->toBe(['id' => 3]);
})->with([
    'bare' => '{"id": 3}',
    'fenced' => "```json\n{\"id\": 3}\n```",
    'chatty' => 'Sure! Here you go: {"id": 3} Hope that helps.',
]);

it('extracts a list from bare, fenced or chatty text', function (string $text) {
    expect(JsonExtractor::list($text))->toBe([['title' => 'a', 'artist' => 'b']]);
})->with([
    'bare' => '[{"title": "a", "artist": "b"}]',
    'fenced' => "```json\n[{\"title\": \"a\", \"artist\": \"b\"}]\n```",
    'chatty' => 'Candidates: [{"title": "a", "artist": "b"}] Done.',
]);

it('keeps nested brackets intact', function () {
    expect(JsonExtractor::object('{"a": {"b": [1, 2]}}'))->toBe(['a' => ['b' => [1, 2]]]);
});

it('returns null when there is nothing usable', function (mixed $text) {
    expect(JsonExtractor::object($text))->toBeNull()
        ->and(JsonExtractor::list($text))->toBeNull();
})->with([
    'not a string' => [null],
    'empty' => [''],
    'no brackets' => ['no json here'],
    'reversed' => ['} then {'],
    'invalid json' => ['{not json}'],
]);
