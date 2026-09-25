<?php

namespace App\Modules\Onboarding\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\PlatformAccount;
use App\Modules\Billing\Services\EntitlementService;
use App\Modules\Billing\Services\Entitlements;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Onboarding\Requests\BusinessSetupRequest;
use App\Modules\Scraping\Jobs\ScrapeBusinessJob;
use App\Modules\Social\Platforms\GoogleBusinessProfilePlatform;
use App\Modules\Social\Services\SocialConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    /** Settings fields holding a posts-per-week cadence, and their platform. */
    private const CADENCE_FIELDS = [
        'posts_per_week_facebook' => 'facebook',
        'posts_per_week_twitter' => 'twitter',
        'posts_per_week_linkedin' => 'linkedin',
        'posts_per_week_gbp' => 'google_business_profile',
    ];

    /**
     * Return the onboarding status for the current user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $business = $user->business;

        if (! $business) {
            return response()->json([
                'step' => 'business_setup',
                'completed' => false,
                'business' => null,
            ]);
        }

        $connectedPlatforms = $business->connectedPlatforms();
        $steps = [
            'business_setup' => $business->exists,
            'platform_connect' => count($connectedPlatforms) > 0,
            'complete' => $business->onboarding_complete,
        ];

        return response()->json([
            'step' => $this->currentStep($steps),
            'completed' => $business->onboarding_complete,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => $business->industry,
                'website_url' => $business->website_url,
                'google_reviews_url' => $business->google_reviews_url,
                'tone' => $business->tone,
                'onboarding_complete' => $business->onboarding_complete,
            ],
            'connected_platforms' => $connectedPlatforms,
            'steps' => $steps,
        ]);
    }

    /**
     * Create a new business during onboarding.
     */
    public function createBusiness(
        BusinessSetupRequest $request,
        SocialConnectionService $connectionService,
        EntitlementService $entitlementService,
        SubscriptionService $subscriptionService
    ): JsonResponse {
        $user = $request->user();
        $existing = $user->businesses()->count();

        if ($existing > 0) {
            // Without new_location this is a returning user re-running the
            // wizard, and the frontend falls back to updating on a 409.
            if (! $request->boolean('new_location')) {
                return response()->json([
                    'message' => 'You already have a business set up. Use PUT to update it.',
                    'error' => 'business_exists',
                ], 409);
            }

            $entitlements = $entitlementService->forUser($user);
            $blocked = $entitlements->addLocationBlockReason($existing, $entitlementService->extraLocations($user));

            if ($blocked === Entitlements::LIMIT_EXTRA_LOCATION) {
                // An extra Agency location costs money, so it is only added
                // once the user has seen the price and said yes.
                if (! $request->boolean('confirm_extra_location')) {
                    return response()->json($entitlementService->limitResponse($blocked, $entitlements), 402);
                }

                try {
                    $subscriptionService->addExtraLocation($user);
                } catch (\Throwable $e) {
                    Log::warning('OnboardingController: Could not add an extra location', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => $e instanceof \InvalidArgumentException
                            ? $e->getMessage()
                            : 'We could not add the extra location to your subscription just now. Please try again, or get in touch.',
                        'error' => 'extra_location_failed',
                    ], 422);
                }
            } elseif ($blocked) {
                return response()->json($entitlementService->limitResponse($blocked, $entitlements), 403);
            }
        }

        $business = Business::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'industry' => $request->industry,
            'website_url' => $request->website_url,
            'google_reviews_url' => $request->google_reviews_url,
            'tone' => $request->tone ?? 'friendly',
            'city' => $request->city,
            'postcode' => $request->postcode,
        ]);

        // Create default settings
        BusinessSetting::create([
            'business_id' => $business->id,
            'auto_approve_posts' => false,
            'approval_window_hours' => 24,
        ]);

        // A new location becomes the one being worked on, so the rest of the
        // wizard and the dashboard afterwards are about it.
        $user->forceFill(['current_business_id' => $business->id])->save();

        // Pick up cached Google tokens (set during Google auth callback)
        // and create the GBP connection immediately so the user doesn't need to connect again.
        // Only for a first business: those tokens belong to the sign-in, not to
        // whichever extra location happens to be added next.
        $tokenData = $existing === 0 ? Cache::get("google_tokens_{$user->id}") : null;
        if ($tokenData) {
            try {
                $connection = $connectionService->upsertConnection(
                    businessId:   $business->id,
                    platform:     'google_business_profile',
                    accessToken:  $tokenData['access_token'],
                    refreshToken: $tokenData['refresh_token'],
                    expiresAt:    $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : now()->addHour(),
                    scopes:       $tokenData['scopes'] ?? [],
                    rawTokenData: $tokenData['raw'] ?? [],
                );

                // If a specific GBP location was chosen in onboarding, create its PlatformAccount
                // directly from the cache (avoids an extra live API call that can be rate-limited).
                $gbpLocationId = $request->gbp_location_id;
                if ($gbpLocationId) {
                    $cachedLocations = Cache::get("gbp_locations_{$user->id}", []);
                    $chosen = collect($cachedLocations)->firstWhere('id', $gbpLocationId);
                    if ($chosen) {
                        $account = PlatformAccount::updateOrCreate(
                            [
                                'connection_id'       => $connection->id,
                                'platform_account_id' => $chosen['id'],
                            ],
                            [
                                'account_name' => $chosen['name'],
                                'account_type' => 'location',
                                'account_url'  => $chosen['url'] ?? null,
                                'metadata'     => $chosen['metadata'] ?? [],
                            ]
                        );
                        // Mark as selected, deselect others
                        $connection->platformAccounts()->where('id', '!=', $account->id)->update(['is_selected' => false]);
                        $account->update(['is_selected' => true]);
                    }
                }

                // Clear both caches — they've served their purpose
                Cache::forget("google_tokens_{$user->id}");
                Cache::forget("gbp_locations_{$user->id}");

                Log::info('OnboardingController: GBP connection created from cached tokens', [
                    'business_id'     => $business->id,
                    'gbp_location_id' => $gbpLocationId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('OnboardingController: Could not create GBP connection from cached tokens', [
                    'business_id' => $business->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // Kick off background scraping immediately
        ScrapeBusinessJob::dispatch($business)->onQueue('scraping');

        return response()->json([
            'message' => 'Business created. We\'re already getting to know your business in the background!',
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => $business->industry,
                'website_url' => $business->website_url,
                'google_reviews_url' => $business->google_reviews_url,
                'tone' => $business->tone,
            ],
        ], 201);
    }

    /**
     * Update the business during or after onboarding.
     */
    public function updateBusiness(BusinessSetupRequest $request): JsonResponse
    {
        $user = $request->user();
        $business = $user->business;

        if (! $business) {
            return response()->json([
                'message' => 'No business found. Please create one first.',
                'error' => 'business_not_found',
            ], 404);
        }

        $websiteChanged = $request->website_url && $request->website_url !== $business->website_url;

        $business->update($request->only([
            'name',
            'industry',
            'website_url',
            'google_reviews_url',
            'tone',
            'city',
            'postcode',
            'phone',
            'address_line_1',
            'address_line_2',
            'description',
        ]));

        // Re-scrape if website URL changed
        if ($websiteChanged) {
            ScrapeBusinessJob::dispatch($business)->onQueue('scraping');
        }

        return response()->json([
            'message' => 'Business updated successfully.',
            'business' => $business->fresh(),
        ]);
    }

    /**
     * Mark onboarding as complete and trigger initial content generation.
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $business = $user->business;

        if (! $business) {
            return response()->json(['message' => 'Business not found.', 'error' => 'business_not_found'], 404);
        }

        if ($business->socialConnections()->where('is_active', true)->doesntExist()) {
            Log::warning('OnboardingController: complete() called with no active connections', [
                'business_id' => $business->id,
                'user_id'     => $user->id,
            ]);
            // Non-blocking — allow completion so the user isn't stuck.
            // The GBP connection should have been created in createBusiness() for Google users.
        }

        $business->update([
            'onboarding_complete' => true,
            'onboarding_completed_at' => now(),
        ]);

        // Trigger immediate content generation
        \App\Modules\Content\Jobs\GeneratePostsJob::dispatch($business)->onQueue('generation');

        return response()->json([
            'message' => 'Setup complete! Your first posts are being generated now.',
            'business' => $business->fresh(),
        ]);
    }

    /**
     * Get the list of available industries.
     */
    public function industries(Request $request): JsonResponse
    {
        $industries = [
            'restaurant' => 'Restaurant & Food',
            'retail' => 'Retail & Shopping',
            'trades' => 'Trades & Construction',
            'professional_services' => 'Professional Services',
            'health_beauty' => 'Health & Beauty',
            'automotive' => 'Automotive',
            'fitness_wellness' => 'Fitness & Wellness',
            'hospitality' => 'Hospitality & Events',
            'education' => 'Education & Training',
            'technology' => 'Technology & IT',
            'property' => 'Property & Estate Agents',
            'legal' => 'Legal & Solicitors',
            'accounting' => 'Accounting & Finance',
            'healthcare' => 'Healthcare & Medical',
            'childcare' => 'Childcare & Nursery',
            'cleaning' => 'Cleaning Services',
            'landscaping' => 'Gardening & Landscaping',
            'photography' => 'Photography & Videography',
            'marketing' => 'Marketing & Design',
            'other' => 'Other',
        ];

        return response()->json(['industries' => $industries]);
    }

    /**
     * Return the user's Google Business Profile locations for the onboarding picker.
     *
     * Priority order:
     *   1. Cached locations (set on a previous successful fetch)
     *   2. Live lookup using the cached raw Google token (new user, no business yet)
     *   3. Live lookup via an existing SocialConnection (returning users)
     *
     * A 90-second rate-limit cooldown prevents hammering the GBP API when Google
     * returns 429. The frontend receives rate_limited: true so it can show a retry button.
     */
    public function gbpLocations(Request $request): JsonResponse
    {
        $user     = $request->user();
        $platform = new GoogleBusinessProfilePlatform();

        // 1. Already have cached locations — return immediately, no API call
        $cached = Cache::get("gbp_locations_{$user->id}");
        if ($cached) {
            return response()->json(['locations' => $cached]);
        }

        // Honour rate-limit cooldown — don't hammer Google while quota is exhausted
        if (Cache::get("gbp_ratelimit_{$user->id}")) {
            Log::info('OnboardingController: gbpLocations skipped — rate-limit cooldown active', ['user_id' => $user->id]);
            return response()->json(['locations' => [], 'rate_limited' => true]);
        }

        // Helper to make the GBP call and cache the result (or set cooldown on 429)
        $fetchAndCache = function (callable $fetchFn) use ($user): array {
            try {
                $locations = $fetchFn();
                if (! empty($locations)) {
                    Cache::put("gbp_locations_{$user->id}", $locations, now()->addHours(2));
                }
                return $locations;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()?->getStatusCode() === 429) {
                    Cache::put("gbp_ratelimit_{$user->id}", true, now()->addSeconds(90));
                    Log::warning('OnboardingController: GBP 429 — cooldown set', ['user_id' => $user->id]);
                    throw $e;
                }
                throw $e;
            }
        };

        // 2. Cached raw Google token (new user — no business/connection yet)
        $tokenData = Cache::get("google_tokens_{$user->id}");
        if ($tokenData && ! empty($tokenData['access_token'])) {
            Log::info('OnboardingController: gbpLocations fetching with cached token', ['user_id' => $user->id]);
            try {
                $locations = $fetchAndCache(fn () => $platform->getAccountsWithToken($tokenData['access_token']));
                return response()->json(['locations' => $locations]);
            } catch (\Throwable) {
                return response()->json(['locations' => [], 'rate_limited' => true]);
            }
        }

        // 3. Live lookup via existing SocialConnection (returning / reconnecting user)
        $connection = $user->business?->socialConnections()
            ->where('platform', 'google_business_profile')
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return response()->json(['locations' => []]);
        }

        try {
            $locations = $fetchAndCache(fn () => $platform->getAccounts($connection));
            return response()->json(['locations' => $locations]);
        } catch (\Throwable) {
            return response()->json(['locations' => [], 'rate_limited' => true]);
        }
    }

    /**
     * Get the current business settings.
     */
    public function settings(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'No business found.'], 404);
        }

        $settings = $business->getOrCreateSettings();
        $entitlements = app(EntitlementService::class)->forUser($request->user());

        return response()->json([
            'business' => $business->only([
                'id', 'name', 'industry', 'website_url', 'google_reviews_url',
                'tone', 'city', 'postcode', 'phone', 'description', 'usp_notes',
            ]),
            'settings' => $settings,
            // Most posts a week each platform may have on this plan, so the
            // cadence picker can show higher options as locked, not missing.
            'posts_per_week_caps' => app(EntitlementService::class)->postsPerWeekCaps($entitlements),
            'plan' => $entitlements->plan,
            'plan_name' => $entitlements->planName,
        ]);
    }

    /**
     * Update business settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        if (! $business) {
            return response()->json(['message' => 'No business found.'], 404);
        }

        $request->validate([
            'auto_approve_posts' => ['nullable', 'boolean'],
            'approval_window_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'post_time_windows' => ['nullable', 'array'],
            'generate_images' => ['nullable', 'boolean'],
            'include_local_news_hooks' => ['nullable', 'boolean'],
            'include_review_content' => ['nullable', 'boolean'],
            'posts_per_week_facebook' => ['nullable', 'integer', 'min:0', 'max:14'],
            'posts_per_week_twitter' => ['nullable', 'integer', 'min:0', 'max:14'],
            'posts_per_week_linkedin' => ['nullable', 'integer', 'min:0', 'max:14'],
            'posts_per_week_gbp' => ['nullable', 'integer', 'min:0', 'max:7'],
            'notify_post_failed' => ['nullable', 'boolean'],
            'notify_weekly_summary' => ['nullable', 'boolean'],
            'notify_token_expiring' => ['nullable', 'boolean'],
        ]);

        // Cadence above the plan is refused with the reason and the upgrade,
        // rather than silently saved and never delivered. The static max above
        // is only a sanity bound; this is the real limit.
        $entitlementService = app(EntitlementService::class);
        $entitlements = $entitlementService->forUser($request->user());

        foreach (self::CADENCE_FIELDS as $field => $platform) {
            $requested = $request->input($field);
            if ($requested === null || ! $entitlements->active) {
                continue;
            }

            if ((int) $requested > $entitlements->postsPerWeekCap($platform)) {
                $refusal = $entitlementService->limitResponse(
                    Entitlements::LIMIT_POSTS_PER_WEEK,
                    $entitlements,
                    ['platform' => $platform]
                );

                return response()->json($refusal + ['errors' => [$field => [$refusal['message']]]], 422);
            }
        }

        $settings = $business->getOrCreateSettings();
        $settings->update($request->all());

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => $settings->fresh(),
        ]);
    }

    private function currentStep(array $steps): string
    {
        if (! $steps['business_setup']) {
            return 'business_setup';
        }
        if (! $steps['platform_connect']) {
            return 'platform_connect';
        }
        if (! $steps['complete']) {
            return 'complete';
        }
        return 'done';
    }
}
