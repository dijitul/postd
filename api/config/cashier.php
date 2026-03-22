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

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 1900, // in pence
            'platform_limit' => 2,
            'tiktok_included' => false,
            'gbp_included' => true,
            'stripe_price_id' => env('STRIPE_PLAN_STARTER'),
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 3900,
            'platform_limit' => 4,
            'tiktok_included' => false,
            'gbp_included' => true,
            'stripe_price_id' => env('STRIPE_PLAN_GROWTH'),
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 6900,
            'platform_limit' => null, // unlimited
            'tiktok_included' => true,
            'gbp_included' => true,
            'stripe_price_id' => env('STRIPE_PLAN_PRO'),
        ],
        'tiktok_addon' => [
            'name' => 'TikTok Add-on',
            'price' => 1500,
            'stripe_price_id' => env('STRIPE_PLAN_TIKTOK_ADDON'),
        ],
    ],

    'trial_days' => 14,
    'trial_plan' => 'growth',

];
