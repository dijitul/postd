<?php

namespace App\Modules\Billing\Services;

use App\Models\AiCostLog;
use App\Models\Business;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The one place that decides what an account may do.
 *
 * Plan limits live in config/plans.php and the rules that apply them live in
 * Entitlements, which is pure and unit tested. This service joins the two to a
 * real user: which plan they are on, whether they are trialling, and how much
 * of each allowance they have used. Callers:
 *
 *  - SocialConnectionController and GoogleAuthController: may a platform be connected
 *  - ContentGenerationService: which platforms, how many posts, how many X posts on trial
 *  - GeneratePostImageJob: is there an AI image left this month
 *  - OnboardingController: posts-per-week settings, and adding a location
 *  - AnalyticsController: how far back history goes
 *  - BillingController: the usage-versus-limits summary on the Billing page
 *
 * Limits are only ever checked before something new is created. Publishing
 * never looks here, so reaching a limit cannot stop a scheduled post.
 */
class EntitlementService
{
    // Upgrade path offered when a limit is reached.
    private const UPGRADE_PATH = [
        'local' => 'growth',
        'growth' => 'agency',
        Entitlements::PLAN_NONE => 'growth',
    ];

    public function __construct(private readonly PlanCatalogue $catalogue) {}

    /**
     * Entitlements for a plan key, with no database involved.
     */
    public function resolve(string $planKey, bool $onTrial = false): Entitlements
    {
        $key = $this->catalogue->normalise($planKey);

        if ($key === null) {
            return Entitlements::none($this->allPlatforms());
        }

        return Entitlements::fromPlan(
            $key,
            $this->catalogue->get($key),
            $onTrial,
            $onTrial ? $this->catalogue->trialCaps() : []
        );
    }

    public function forUser(?User $user): Entitlements
    {
        if (! $user) {
            return Entitlements::none($this->allPlatforms());
        }

        $plan = $user->activePlanName();
        $onTrial = ! $user->isComped() && ! $user->subscribed('default') && $user->isOnValidTrial();

        // A paying customer whose Stripe price is missing from config/plans.php
        // gets the trial plan rather than nothing. That is a setup gap on our
        // side, and they should not lose their posts over it.
        if ($plan === 'unknown') {
            Log::warning('EntitlementService: subscribed user has an unrecognised Stripe price', [
                'user_id' => $user->id,
                'price' => $user->subscription('default')?->stripe_price,
            ]);
            $plan = $this->catalogue->trialPlan();
        }

        return $this->resolve($plan, $onTrial);
    }

    public function forBusiness(Business $business): Entitlements
    {
        return $this->forUser($business->user);
    }

    // ── Usage ──────────────────────────────────────────────────────────────

    /**
     * AI images used in the current allowance period, pooled across every
     * location the account owns. A trial counts every image since sign-up.
     */
    public function aiImagesUsed(User $user, Entitlements $entitlements): int
    {
        $query = AiCostLog::query()
            ->whereIn('business_id', $user->businesses()->withTrashed()->select('id'))
            ->where('operation', 'image_generation');

        if ($entitlements->imagePeriod === Entitlements::IMAGE_PERIOD_MONTH) {
            $query->where('created_at', '>=', now()->startOfMonth());
        }

        return $query->count();
    }

    /** X posts written for the account so far, for the trial cap. */
    public function twitterPostsUsed(User $user): int
    {
        return Post::query()
            ->whereIn('business_id', $user->businesses()->withTrashed()->select('id'))
            ->where('platform', 'twitter')
            ->where('status', '!=', Post::STATUS_REJECTED)
            ->count();
    }

    /** Paid-for locations on top of the plan's included ones. */
    public function extraLocations(User $user): int
    {
        $priceId = $this->catalogue->extraLocationPriceId();
        $subscription = $user->subscription('default');

        if (! $priceId || ! $subscription || ! $subscription->valid()) {
            return 0;
        }

        $item = $subscription->items->firstWhere('stripe_price', $priceId);

        return (int) ($item?->quantity ?? 0);
    }

    public function aiImagesRemaining(Business $business): int
    {
        $user = $business->user;
        $entitlements = $this->forUser($user);

        return $user ? $entitlements->aiImagesRemaining($this->aiImagesUsed($user, $entitlements)) : 0;
    }

    /** X posts left on the trial, or null when X is not capped in total. */
    public function twitterPostsRemaining(Business $business): ?int
    {
        $user = $business->user;
        $entitlements = $this->forUser($user);

        if ($entitlements->twitterPostCap === null || ! $user) {
            return null;
        }

        return $entitlements->twitterPostsRemaining($this->twitterPostsUsed($user));
    }

    // ── Decisions ──────────────────────────────────────────────────────────

    /**
     * The friendly refusal for connecting $platform, or null if it is allowed.
     */
    public function connectRefusal(User $user, Business $business, string $platform): ?array
    {
        $entitlements = $this->forUser($user);
        $reason = $entitlements->connectBlockReason($platform, $business->connectedPlatforms());

        return $reason ? $this->limitResponse($reason, $entitlements, ['platform' => $platform]) : null;
    }

    /**
     * Connected platforms generation may write for, oldest connection first.
     *
     * @return string[]
     */
    public function usablePlatforms(Business $business): array
    {
        return $this->forBusiness($business)->usablePlatforms($business->connectedPlatforms());
    }

    /**
     * Most posts a week each platform may have, for the Settings page.
     *
     * @return array<string, int>
     */
    public function postsPerWeekCaps(Entitlements $entitlements): array
    {
        $caps = [];
        foreach ($this->allPlatforms() as $platform) {
            $caps[$platform] = $entitlements->postsPerWeekCap($platform);
        }

        return $caps;
    }

    // ── Messages ───────────────────────────────────────────────────────────

    /** The plan to suggest when $plan runs out of room. */
    public function upgradeFor(string $plan): ?string
    {
        return self::UPGRADE_PATH[$this->catalogue->normalise($plan) ?? $plan] ?? null;
    }

    /**
     * A refusal the frontend can show as it is: what the limit is, why, and
     * where to go to lift it. Never an error code on its own.
     */
    public function limitResponse(string $reason, Entitlements $entitlements, array $context = []): array
    {
        $upgrade = $this->upgradeFor($entitlements->plan);
        $upgradeName = $upgrade ? $this->catalogue->name($upgrade) : null;
        $upgradePrice = $upgrade ? $this->priceLabel($this->catalogue->pricePence($upgrade)) : null;
        $platform = $this->platformName($context['platform'] ?? '');

        $message = match ($reason) {
            Entitlements::LIMIT_PLATFORM_NOT_INCLUDED => ($context['platform'] ?? null) === 'twitter'
                ? "X charges for every post it publishes, which is why it is not part of the {$entitlements->planName} plan. {$upgradeName} adds X and every other platform for {$upgradePrice}."
                : "{$platform} is not included on the {$entitlements->planName} plan. {$upgradeName} includes it for {$upgradePrice}.",
            Entitlements::LIMIT_PLATFORM_COUNT => "Your {$entitlements->planName} plan covers any {$entitlements->platformLimit} platforms, and you already have {$entitlements->platformLimit} connected. {$upgradeName} adds every platform, including X, for {$upgradePrice}. Or disconnect one to swap it for {$platform}.",
            Entitlements::LIMIT_POSTS_PER_WEEK => "Your {$entitlements->planName} plan includes up to {$entitlements->postsPerWeekCap($context['platform'] ?? '')} posts a week on {$platform}."
                .($upgrade ? " {$upgradeName} raises that to {$this->resolve($upgrade)->postsPerWeekCap($context['platform'] ?? '')} for {$upgradePrice}." : ''),
            Entitlements::LIMIT_EXTRA_LOCATION => "Your {$entitlements->planName} plan includes {$entitlements->locations} locations. Each extra location is {$this->priceLabel($this->catalogue->extraLocationPricePence())}, added to your subscription.",
            Entitlements::LIMIT_LOCATIONS => $entitlements->active
                ? "Your {$entitlements->planName} plan covers {$this->locationsLabel($entitlements->locations)}. Agency covers 3 locations for {$this->priceLabel($this->catalogue->pricePence('agency'))}."
                : 'Choose a plan to add another location.',
            default => 'That is not included on your current plan.',
        };

        return array_filter([
            'error' => $reason,
            'message' => $message,
            'plan' => $entitlements->plan,
            'upgrade_plan' => $reason === Entitlements::LIMIT_LOCATIONS ? 'agency' : $upgrade,
            'upgrade_url' => '/billing',
        ], fn ($value) => $value !== null);
    }

    // ── Summary ────────────────────────────────────────────────────────────

    /**
     * Everything the Billing page shows about limits and usage.
     */
    public function summary(User $user, ?Business $business = null): array
    {
        $entitlements = $this->forUser($user);
        $connected = $business?->connectedPlatforms() ?? [];
        $usable = $entitlements->usablePlatforms($connected);
        $extraLocations = $this->extraLocations($user);
        $imagesUsed = $this->aiImagesUsed($user, $entitlements);

        return [
            'entitlements' => $entitlements->toArray(),
            'posts_per_week_caps' => $this->postsPerWeekCaps($entitlements),
            'usage' => [
                'ai_images_used' => $imagesUsed,
                'ai_images_remaining' => $entitlements->aiImagesRemaining($imagesUsed),
                'twitter_posts_used' => $entitlements->twitterPostCap !== null ? $this->twitterPostsUsed($user) : null,
                'platforms_connected' => $connected,
                'platforms_active' => $usable,
                // Connected but not written for on this plan. Shown as paused.
                'platforms_paused' => array_values(array_diff($connected, $usable)),
                'locations' => $user->businesses()->count(),
                'location_limit' => $entitlements->locationLimit($extraLocations),
                'extra_locations' => $extraLocations,
            ],
            'upgrade_plan' => $this->upgradeFor($entitlements->plan),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** @return string[] */
    private function allPlatforms(): array
    {
        return Post::PLATFORMS;
    }

    private function priceLabel(?int $pence): string
    {
        return $pence === null ? '' : '£'.rtrim(rtrim(number_format($pence / 100, 2), '0'), '.').' a month';
    }

    private function locationsLabel(int $count): string
    {
        return $count === 1 ? '1 location' : "{$count} locations";
    }

    private function platformName(string $platform): string
    {
        return match ($platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter' => 'X',
            'linkedin' => 'LinkedIn',
            'facebook' => 'Facebook',
            default => ucfirst($platform),
        };
    }
}
