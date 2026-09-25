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

    // LinkedIn is NOT handled by Socialite. The Community Management API must be
    // the only product on a developer app, so there is no OpenID Connect and no
    // profile scope — and every Socialite LinkedIn driver fetches a profile
    // endpoint (/v2/me or /v2/userinfo) to build its user object, so both throw
    // on callback. SocialConnectionController does the OAuth exchange by hand.
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
        // Versioned REST endpoints (/rest/*) reject any request without a
        // LinkedIn-Version header, and each version is retired roughly a year
        // after release. Confirm against LinkedIn's current docs before going
        // live, and bump it through env rather than editing this default.
        'version' => env('LINKEDIN_API_VERSION', '202608'),
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
        // Places API (New). The legacy maps.googleapis.com/maps/api/place
        // endpoints are a different product and a different host.
        'base_url' => 'https://places.googleapis.com',
    ],

    // LinkVine URL shortener. lnkz.uk is the shortest domain on the account,
    // which is the whole point when the link has to fit in a tweet.
    'linkvine' => [
        'key' => env('LINKVINE_API_KEY'),
        'base_url' => env('LINKVINE_BASE_URL', 'https://linkvine.uk/api'),
        'domain' => env('LINKVINE_DOMAIN', 'lnkz.uk'),
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

    // Post images. DALL-E 3 was withdrawn from our key and every image job
    // failed from April 2026 until this moved to the GPT Image models. Model
    // and quality are env-driven so a model retirement is an .env change,
    // not a deploy. cost_usd is our per-image estimate for the cost log.
    'openai_images' => [
        'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1-mini'),
        'quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'cost_usd' => (float) env('OPENAI_IMAGE_COST_USD', 0.015),
    ],

];
