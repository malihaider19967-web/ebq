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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'serper' => [
        'key' => env('SERPER_API_KEY'),
        'search_url' => env('SERPER_SEARCH_URL', 'https://google.serper.dev/search'),
    ],

    'lighthouse' => [
        'url' => env('LIGHTHOUSE_API_URL'),
        'key' => env('LIGHTHOUSE_API_KEY'),
        'timeout' => (int) env('LIGHTHOUSE_TIMEOUT_S', 90),
    ],

    'keywords_everywhere' => [
        'key' => env('KEYWORDS_EVERYWHERE_API_KEY'),
        'base_url' => env('KEYWORDS_EVERYWHERE_BASE_URL', 'https://api.keywordseverywhere.com'),
        'fresh_days' => (int) env('KEYWORDS_EVERYWHERE_FRESH_DAYS', 30),
    ],

];
