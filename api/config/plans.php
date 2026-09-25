<?php

/*
|--------------------------------------------------------------------------
| Plans and entitlements
|--------------------------------------------------------------------------
|
| The single source of truth for what postd sells and what each plan may do.
| BillingController serves this to the Billing page, EntitlementService
| enforces it, and the landing page copy in web/src/lib/plans.js mirrors it.
| Change a limit here and every enforcement point follows.
|
| Prices are in pence. An annual or Agency Stripe price that has not been set
| up yet is left null, and the Billing page hides that option rather than
| offering a checkout that would fail.
|
*/

$allPlatforms = ['google_business_profile', 'facebook', 'linkedin', 'twitter'];

// Agency and the legacy Pro plan share entitlements: Pro customers lost TikTok
// and Instagram, so they keep their price and gain what Agency offers.
$agencyEntitlements = [
    'locations' => 3,
    'extra_locations' => true,
    'platform_limit' => null,
    'platforms' => $allPlatforms,
    'posts_per_week' => 14,
    // More than 7 GBP posts a week does nothing for ranking, so it is not offered.
    'posts_per_week_by_platform' => ['google_business_profile' => 7],
    // Pooled across every location on the account, not per location.
    'ai_images_per_month' => 100,
    'analytics_days' => 365,
];

return [

    'trial_days' => 14,

    // Trials run on this plan's entitlements, less the trial caps below.
    'trial_plan' => 'growth',

    // X costs us per post and images cost per image, so a no-card trial is
    // capped in total rather than per month. Under £1 of exposure per trial.
    'trial_caps' => [
        'twitter_posts' => 10,
        'ai_images' => 10,
    ],

    // Plan keys that were renamed. Old Stripe metadata, comped_plan values and
    // anything else still saying 'starter' resolves to the new key.
    'aliases' => [
        'starter' => 'local',
    ],

    // Agency add-on for each location beyond the three included.
    'extra_location' => [
        'price' => 1900,
        'stripe_price_id' => env('STRIPE_PRICE_EXTRA_LOCATION'),
    ],

    'plans' => [

        'local' => [
            'name' => 'Local',
            'tagline' => 'Everything one business needs on the two channels that matter most to it.',
            'offered' => true,
            'popular' => false,
            'monthly' => [
                'price' => 1900,
                // Reuses the old Starter price so existing subscribers need no change.
                'stripe_price_id' => env('STRIPE_PLAN_LOCAL', env('STRIPE_PLAN_STARTER')),
            ],
            'annual' => [
                'price' => 19000,
                'stripe_price_id' => env('STRIPE_PLAN_LOCAL_ANNUAL'),
            ],
            'locations' => 1,
            'extra_locations' => false,
            // Any two of the platforms below. X is left out because it is the
            // only channel with a per-post cost to us.
            'platform_limit' => 2,
            'platforms' => ['google_business_profile', 'facebook', 'linkedin'],
            'posts_per_week' => 3,
            'posts_per_week_by_platform' => [],
            'ai_images_per_month' => 5,
            'analytics_days' => 30,
        ],

        'growth' => [
            'name' => 'Growth',
            'tagline' => 'Every platform, including X, at up to a post a day.',
            'offered' => true,
            'popular' => true,
            'monthly' => [
                'price' => 3900,
                'stripe_price_id' => env('STRIPE_PLAN_GROWTH'),
            ],
            'annual' => [
                'price' => 39000,
                'stripe_price_id' => env('STRIPE_PLAN_GROWTH_ANNUAL'),
            ],
            'locations' => 1,
            'extra_locations' => false,
            'platform_limit' => null,
            'platforms' => $allPlatforms,
            'posts_per_week' => 7,
            'posts_per_week_by_platform' => [],
            'ai_images_per_month' => 30,
            'analytics_days' => 365,
        ],

        'agency' => [
            'name' => 'Agency',
            'tagline' => 'For multi-site owners and small agencies running a handful of locations.',
            'offered' => true,
            'popular' => false,
            'monthly' => [
                'price' => 7900,
                'stripe_price_id' => env('STRIPE_PLAN_AGENCY'),
            ],
            'annual' => [
                'price' => 79000,
                'stripe_price_id' => env('STRIPE_PLAN_AGENCY_ANNUAL'),
            ],
        ] + $agencyEntitlements,

        // Legacy. Not sold to new customers, but existing Pro subscribers keep
        // their £69 price for as long as they stay subscribed.
        'pro' => [
            'name' => 'Pro',
            'tagline' => 'Legacy plan with Agency features at your original price.',
            'offered' => false,
            'popular' => false,
            'monthly' => [
                'price' => 6900,
                'stripe_price_id' => env('STRIPE_PLAN_PRO'),
            ],
            'annual' => [
                'price' => null,
                'stripe_price_id' => null,
            ],
        ] + $agencyEntitlements,

    ],

];
