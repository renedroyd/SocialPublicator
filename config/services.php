<?php

return [

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
        'workspace_id' => env('BOT_API_WORKSPACE_ID'),
        'command_bot_name' => env('BOT_COMMAND_BOT_NAME', 'WBot'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
        'permissions' => ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'],
    ],

    'dubai_shop' => [
        'campaign_dispatch_enabled' => (bool) env('DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED', false),
        'api_base_url' => env('DUBAI_SHOP_API_BASE_URL'),
        'api_token' => env('DUBAI_SHOP_API_TOKEN'),
        'timeout' => (int) env('DUBAI_SHOP_TIMEOUT', 15),
        'webhook_keys' => json_decode((string) env('DUBAI_SHOP_WEBHOOK_KEYS', '{}'), true) ?: [],
        'workspace_id' => env('DUBAI_SHOP_WORKSPACE_ID'),
        'webhook_tolerance_seconds' => (int) env('DUBAI_SHOP_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

];
