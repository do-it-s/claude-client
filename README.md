# claude-client

A small Claude Messages API client for Laravel.

- **One API key per feature**, so the Claude Platform cost view breaks usage down by what your application used Claude for.
- **A full JSONL log of every call**, request and response, per feature.
- A **vision helper** that sends an image plus a prompt and returns what the model filled into a tool schema.
- A **JSON extractor** for model answers that wrap their JSON in a code fence or a sentence.

The package does not ship model names, `max_tokens` or timeouts: you decide those per call.

## Requirements

- PHP ^8.3, Laravel ^13.0

## Installation

```
composer require do-it-s/claude-client
```

Optionally publish the config:

```
php artisan vendor:publish --tag=claude-client-config
```

## Configuration

`config/claude-client.php`:

| Key | Default | Purpose |
|---|---|---|
| `api_key` | `env('ANTHROPIC_API_KEY')` | Shared key, used by every feature without a key of its own |
| `api_keys` | `[]` | Feature name => API key |
| `log_disabled` | `env('ANTHROPIC_LOG_DISABLED', false)` | Stop writing the request log (e.g. in tests) |

Feature names are yours to choose. Fill `api_keys` in the published config (`env()` is not available once the config is cached, so the package does not derive variable names for you):

```php
'api_keys' => [
    'lyrics' => env('ANTHROPIC_API_KEY_LYRICS'),
    'chart-reading' => env('ANTHROPIC_API_KEY_CHART'),
],
```

## Sending a message

```php
use DoITs\ClaudeClient\ClaudeMessages;

$response = app(ClaudeMessages::class)->send(
    feature: 'lyrics',
    purpose: 'song-name-normalizer', // which call within the feature; recorded in the log
    body: [
        'model' => 'claude-sonnet-5-5',
        'max_tokens' => 1024,
        'messages' => [['role' => 'user', 'content' => 'Hello']],
    ],
    timeoutSeconds: 30,
);

if ($response->failed()) {
    report($response->errorMessage());
}

$response->text();             // text blocks, concatenated
$response->toolInput('name');  // input the model filled into a tool, or null
$response->usage();
$response->model();
$response->stopReason();
$response->requestId();
$response->json('content.0.type');
```

`send()` returns a `DoITs\ClaudeClient\MessagesResponse`, never the HTTP client's response type. The body is passed through as given.

Failures:

- `ApiKeyMissingException` when neither the feature nor the shared key is set (nothing is sent).
- `TransportException` when the request itself fails (connection error, timeout). The failure is logged first.
- An API error status is **not** an exception: check `$response->failed()`.

All of them extend `ClaudeClientException`.

## The request log

Every call appends one JSON line to `storage/logs/anthropic-{feature}.log`: `purpose`, `duration_ms`, `status`, `request_id`, `request`, `response` and `error`. The API key is never recorded, base64 image data is replaced by `[omitted: N base64 chars]`, and a failure to write the log never fails the call. The file is not rotated.

## Reading an image

```php
use DoITs\ClaudeClient\Vision\ClaudeVisionClient;

$result = app(ClaudeVisionClient::class)->read(
    image: $bytes,               // JPEG, PNG, GIF or WebP
    prompt: 'Read the chart.',
    inputSchema: $jsonSchema,    // JSON Schema of the result
    feature: 'chart-reading',
    model: 'claude-sonnet-5-5',
    maxTokens: 24000,
    timeoutSeconds: 540,
);

$result['input'];  // what the model recorded, matching $inputSchema
$result['usage'];
$result['model'];
```

It throws `ClaudeClientException` when the key is missing, the file is not a supported image, the request fails, the API returns an error status, the answer was cut off at `max_tokens`, or the model did not call the tool.

## Extracting JSON from a model answer

```php
use DoITs\ClaudeClient\Support\JsonExtractor;

JsonExtractor::object($text); // outermost {...} decoded, or null
JsonExtractor::list($text);   // outermost [...] decoded, or null
```

## Testing

Requests go through Laravel's HTTP client, so `Http::fake()` works. Set `claude-client.log_disabled` to `true` to keep the log files out of your test runs. To replace the transport entirely, bind your own `DoITs\ClaudeClient\Contracts\MessagesTransport`.

## License

MIT
