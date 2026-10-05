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

    'openweathermap' => [
        'key' => env('OWM_API_KEY'),
        'base_url' => env('OWM_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
        'units' => env('OWM_UNITS', 'metric'),
        'lang' => env('OWM_LANG', 'en'),
        'timeout' => (int) env('OWM_TIMEOUT', 10),
        'retry_times' => (int) env('OWM_RETRY_TIMES', 2),
        'retry_sleep' => (int) env('OWM_RETRY_SLEEP', 200),
        'cache_ttl' => (int) env('OWM_CACHE_TTL', 600),
    ],

];
