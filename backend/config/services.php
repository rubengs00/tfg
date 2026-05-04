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

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'market' => env('SPOTIFY_MARKET', 'ES'),
        // SOLO para desarrollo local (Windows/Laragon a veces no tiene CA bundle configurado)
        'skip_ssl_verify' => env('SPOTIFY_SKIP_SSL_VERIFY', false),
        // Ruta absoluta a curl.exe (porque el PATH de PHP/Apache puede no incluirlo)
        'curl_path' => env('SPOTIFY_CURL_PATH', 'curl'),
        'seed_artists' => [
            'Rojuu',
            'Saramalacara',
            'Maretu',
            'Radiohead',
            'Deftones',
        ],
    ],
];
