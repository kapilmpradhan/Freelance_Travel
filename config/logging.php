<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => 'stack',

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'console' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stdout',
            ],
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'loki_json' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel-loki.json'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'console'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Write Error/Exception to File
    |--------------------------------------------------------------------------
    |
    | When enabled, error and exception level logs will also be written
    | to CSV files.
    |
    */
    'write_errors_to_file' => env('LOG_WRITE_ERRORS_TO_FILE', false),

    'log_files' => [
        // User related
        'account_verification' => 'verify-account.csv',
        'forgot_password' => 'forgot-password.csv',
        'fcm_subscription' => 'fcm-subscription.csv',
        'user_activity' => 'user-activity.csv',
        'user_auth' => 'user-auth.csv',
        'user_otp' => 'user-otp.csv',
        'user_jwt' => 'user-jwt.csv',
        'user_profile' => 'user-profile.csv',
        // Cart and Quote
        'cart' => 'cart.csv',
        'quote' => 'quote.csv',
        // Order and Booking
        'order' => 'order.csv',
        'booking' => 'booking.csv',
        'payment' => 'payment.csv',
        // External API
        'tdms' => 'tdms.csv',
        // Products
        'products' => 'products.csv',
        // Agent
        'agent' => 'agent.csv',
        // Email
        'email' => 'email.csv',
        // Cache
        'cache' => 'cache.csv',
        // Discount/Commission
        'discount' => 'discount.csv',
        // Favourites
        'favourites' => 'favourites.csv',
        // Notifications
        'notifications' => 'notifications.csv',
        // Errors
        'errors' => 'errors.csv',
    ]
];
