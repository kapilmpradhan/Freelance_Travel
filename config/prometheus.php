<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prometheus Metrics Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('PROMETHEUS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Storage Configuration
    |--------------------------------------------------------------------------
    | Uses Redis for persistent metric storage across workers
    */
    'storage' => [
        'adapter' => 'redis',
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => env('PROMETHEUS_REDIS_DB', 2),
        'prefix' => env('PROMETHEUS_PREFIX', 'app_metrics_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Namespace
    |--------------------------------------------------------------------------
    | Prefix for all metrics (e.g., travel_app_http_requests_total)
    */
    'namespace' => env('PROMETHEUS_NAMESPACE', 'travel_app'),

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection Settings
    |--------------------------------------------------------------------------
    */
    'collect' => [
        'http' => true,
        'database' => true,
        'queue' => true,
        'business' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Labels Configuration
    |--------------------------------------------------------------------------
    | Normalize route paths to avoid high cardinality
    */
    'route_normalization' => [
        '/\/\d+/' => '/{id}',
        '/\/[a-f0-9-]{36}/' => '/{uuid}',
    ],
];