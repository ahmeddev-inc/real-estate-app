<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'graphql'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8000')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Query-Cache',
        'X-Response-Time',
    ],

    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', true),

    /*
    |--------------------------------------------------------------------------
    | CORS for Specific Environments
    |--------------------------------------------------------------------------
    */
    'environments' => [
        'local' => [
            'allowed_origins' => ['http://localhost:3000', 'http://localhost:8000', 'http://127.0.0.1:3000'],
            'supports_credentials' => true,
        ],
        
        'staging' => [
            'allowed_origins' => [
                'https://staging.aakerz.com',
                'https://admin-staging.aakerz.com',
                env('STAGING_FRONTEND_URL', 'https://staging-frontend.aakerz.com'),
            ],
            'supports_credentials' => true,
        ],
        
        'production' => [
            'allowed_origins' => [
                'https://aakerz.com',
                'https://www.aakerz.com',
                'https://admin.aakerz.com',
                'https://app.aakerz.com',
            ],
            'supports_credentials' => true,
            'max_age' => 172800, // 2 days
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'security_headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => env('CSP_HEADER', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;"),
    ],
];
