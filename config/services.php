<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shop' => [
        'api_url' => env('SHOP_API_URL', 'http://host.docker.internal/api'),
        'rate_limit' => env('SHOP_API_RATE_LIMIT', 3),
        'time_window' => env('SHOP_API_TIME_WINDOW', 60),
        'retry_after_header' => env('SHOP_API_RETRY_AFTER_HEADER', 'Retry-After'),
        'batch_size_limit' => env('SHOP_BATCH_SIZE_LIMIT', 1000)
    ],
];
