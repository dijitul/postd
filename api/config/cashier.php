<?php

return [

    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('CASHIER_WEBHOOK_TOLERANCE', 300),
    ],
    'currency' => env('CASHIER_CURRENCY', 'gbp'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en_GB'),
    'payment_notification' => null,
    'payment_method_types' => ['card'],
    'logger' => env('CASHIER_LOGGER'),
    'calculate_taxes' => true,

    // Plans, prices and limits live in config/plans.php, the single source of
    // truth for billing and entitlements.

];
