<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This connection is used when another is
    | not explicitly specified when executing a given caching function.
    |
    */

    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "apc", "array", "database", "file",
    |         "memcached", "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => 'default',
            'options' => [
                'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
            ],
        ],

        // تكوين خاص لـ Query Cache
        'query_cache' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUERY_CACHE_CONNECTION', 'cache'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_')) . '_query_cache:',
            'ttl' => env('QUERY_CACHE_TTL', 300), // 5 دقائق افتراضيًا
        ],

        // تكوين خاص لـ Model Cache
        'model_cache' => [
            'driver' => 'redis',
            'connection' => env('REDIS_MODEL_CACHE_CONNECTION', 'cache'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_')) . '_model_cache:',
            'ttl' => env('MODEL_CACHE_TTL', 3600), // ساعة افتراضيًا
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, or DynamoDB cache
    | stores there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),

    /*
    |--------------------------------------------------------------------------
    | Query Cache Configuration
    |--------------------------------------------------------------------------
    */
    'query_cache' => [
        'enabled' => env('QUERY_CACHE_ENABLED', true),
        'ttl' => env('QUERY_CACHE_TTL', 300),
        'store' => 'query_cache',
        'exclude_tables' => explode(',', env('QUERY_CACHE_EXCLUDE_TABLES', 'sessions,cache,jobs,failed_jobs')),
        'max_cache_size' => env('QUERY_CACHE_MAX_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Cache Configuration
    |--------------------------------------------------------------------------
    */
    'model_cache' => [
        'enabled' => env('MODEL_CACHE_ENABLED', true),
        'ttl' => env('MODEL_CACHE_TTL', 3600),
        'store' => 'model_cache',
        'prefix' => 'model:',
    ],
];
