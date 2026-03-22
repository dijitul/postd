<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'webhooks/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https://postd.uk'),
        'https://www.postd.uk',
    ],

    'allowed_origins_patterns' => [
        // Allow localhost variants in development
        '/^https?:\/\/localhost(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
