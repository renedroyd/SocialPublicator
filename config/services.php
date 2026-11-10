<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bot_api' => [
        'token' => env('BOT_API_TOKEN'),
        'command_bot_name' => env('BOT_COMMAND_BOT_NAME', 'WBot'),
    ],

    'dubai_shop' => [
        'campaign_dispatch_enabled' => (bool) env('DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED', false),
        'api_base_url' => env('DUBAI_SHOP_API_BASE_URL'),
        'api_token' => env('DUBAI_SHOP_API_TOKEN'),
        'timeout' => (int) env('DUBAI_SHOP_TIMEOUT', 15),
        'webhook_keys' => json_decode((string) env('DUBAI_SHOP_WEBHOOK_KEYS', '{}'), true) ?: [],
        'webhook_tolerance_seconds' => (int) env('DUBAI_SHOP_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

];
