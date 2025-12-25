<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limiter
    |--------------------------------------------------------------------------
    */
    'default' => env('RATE_LIMIT_DEFAULT', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiters Configuration
    |--------------------------------------------------------------------------
    */
    'limiters' => [
        'api' => [
            'driver' => 'redis',
            'key' => 'api_limit',
            'max_attempts' => env('API_RATE_LIMIT', 60),
            'decay_seconds' => env('API_RATE_DECAY', 60),
            'response_callback' => null,
        ],

        'auth' => [
            'driver' => 'redis',
            'key' => 'auth_limit',
            'max_attempts' => env('AUTH_RATE_LIMIT', 5),
            'decay_seconds' => env('AUTH_RATE_DECAY', 300),
            'response_callback' => null,
        ],

        'search' => [
            'driver' => 'redis',
            'key' => 'search_limit',
            'max_attempts' => env('SEARCH_RATE_LIMIT', 30),
            'decay_seconds' => env('SEARCH_RATE_DECAY', 60),
            'response_callback' => null,
        ],

        'uploads' => [
            'driver' => 'redis',
            'key' => 'upload_limit',
            'max_attempts' => env('UPLOAD_RATE_LIMIT', 10),
            'decay_seconds' => env('UPLOAD_RATE_DECAY', 300),
            'response_callback' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'connection' => 'default',
        'prefix' => 'rate_limit:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    */
    'responses' => [
        'too_many_attempts' => [
            'message' => 'لقد تجاوزت الحد المسموح به من الطلبات.',
            'code' => 429,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass Configuration
    |--------------------------------------------------------------------------
    */
    'bypass' => [
        'enabled' => env('RATE_LIMIT_BYPASS_ENABLED', false),
        'tokens' => explode(',', env('RATE_LIMIT_BYPASS_TOKENS', '')),
        'ips' => explode(',', env('RATE_LIMIT_BYPASS_IPS', '')),
    ],
];
