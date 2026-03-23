<?php

namespace App\Modules\Onboarding\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Modules\Onboarding\Requests\BusinessSetupRequest;
use App\Modules\Scraping\Jobs\ScrapeBusinessJob;
use App\Modules\Social\Platforms\GoogleBusinessProfilePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OnboardingController extends Controller
{
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
    public function createBusiness(BusinessSetupRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only allow one business per user for now (can expand later)
        if ($user->businesses()->exists()) {
            return response()->json([
                'message' => 'You already have a business set up. Use PUT to update it.',
                'error' => 'business_exists',
            ], 409);
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
            return response()->json([
                'message' => 'Please connect at least one social platform before completing setup.',
                'error' => 'no_platforms_connected',
            ], 422);
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
     * Works from cached locations (set during Google auth) or live from the connection.
     */
    public function gbpLocations(Request $request): JsonResponse
    {
        $user = $request->user();

        // Prefer cached locations from the Google auth flow
        $cached = Cache::get("gbp_locations_{$user->id}");
        if ($cached) {
            return response()->json(['locations' => $cached]);
        }

        // Fall back to live lookup via existing connection
        $connection = $user->business?->socialConnections()
            ->where('platform', 'google_business_profile')
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return response()->json(['locations' => []]);
        }

        $locations = (new GoogleBusinessProfilePlatform())->getAccounts($connection);
        return response()->json(['locations' => $locations]);
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

        return response()->json([
            'business' => $business->only([
                'id', 'name', 'industry', 'website_url', 'google_reviews_url',
                'tone', 'city', 'postcode', 'phone', 'description', 'usp_notes',
            ]),
            'settings' => $settings,
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
            'posts_per_week_instagram' => ['nullable', 'integer', 'min:0', 'max:14'],
            'posts_per_week_twitter' => ['nullable', 'integer', 'min:0', 'max:21'],
            'posts_per_week_linkedin' => ['nullable', 'integer', 'min:0', 'max:7'],
            'posts_per_week_tiktok' => ['nullable', 'integer', 'min:0', 'max:7'],
            'posts_per_week_gbp' => ['nullable', 'integer', 'min:0', 'max:7'],
            'notify_post_failed' => ['nullable', 'boolean'],
            'notify_weekly_summary' => ['nullable', 'boolean'],
            'notify_token_expiring' => ['nullable', 'boolean'],
        ]);

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
