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

    'ai_service' => [
        'base_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'internal_token' => env('AI_INTERNAL_TOKEN', env('AI_SERVICE_TOKEN', '')),
        'token' => env('AI_INTERNAL_TOKEN', env('AI_SERVICE_TOKEN', '')),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 15),
        'algorithm_version' => env('AI_ALGORITHM_VERSION', 'hybrid-v1'),
        'recommendation_pool_limit' => (int) env('AI_RECOMMENDATION_CANDIDATE_POOL_LIMIT', 50),
    ],

];
