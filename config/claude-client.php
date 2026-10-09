<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared API key
    |--------------------------------------------------------------------------
    |
    | Used for every feature that has no key of its own in "api_keys" below.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Per-feature API keys
    |--------------------------------------------------------------------------
    |
    | Feature name => API key. Giving each feature its own key makes the Claude
    | Platform cost view break usage down per feature. The feature names are
    | whatever your application calls its Claude-backed features, for example:
    |
    |     'lyrics' => env('ANTHROPIC_API_KEY_LYRICS'),
    |
    */

    'api_keys' => [],

    /*
    |--------------------------------------------------------------------------
    | Request log
    |--------------------------------------------------------------------------
    |
    | Every call is appended, request and response in full, to
    | storage/logs/anthropic-{feature}.log as one JSON object per line. Set
    | this to true to stop writing it (e.g. in tests).
    |
    */

    'log_disabled' => env('ANTHROPIC_LOG_DISABLED', false),

];
