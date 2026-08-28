<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
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

    // Social OAuth providers
    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'instagram' => [
        'client_id' => env('INSTAGRAM_APP_ID'),
        'client_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    // Socialite uses 'twitter-oauth-2' as the driver name for OAuth 2.0 PKCE
    'twitter-oauth-2' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),         // GBP platform connect callback
        'auth_redirect' => env('GOOGLE_AUTH_REDIRECT_URI'),    // Login/register callback
    ],

    // AI providers
    'anthropic' => [
        'key'     => env('ANTHROPIC_API_KEY'),
        'version' => '2023-06-01',
        'base_url' => 'https://api.anthropic.com/v1',
    ],

    // External API keys
    'google_places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'creatomate' => [
        'key' => env('CREATOMATE_API_KEY'),
        'base_url' => 'https://api.creatomate.com/v1',
    ],

    'newsapi' => [
        'key' => env('NEWS_API_KEY'),
        'country' => env('NEWS_API_COUNTRY', 'gb'),
        'base_url' => 'https://newsapi.org/v2',
    ],

    // Transactional email. The API key is NOT an SMTP password — SMTP2GO's SMTP
    // servers want a separate SMTP user credential, so we send over their API.
    'smtp2go' => [
        'key' => env('SMTP2GO_API_KEY'),
        'endpoint' => env('SMTP2GO_ENDPOINT', 'https://api.smtp2go.com/v3/email/send'),
    ],

];
