<?php

namespace App\Modules\Billing\Services;

use Carbon\CarbonInterface;

/**
 * What one account may do right now, worked out from its plan and trial state.
 *
 * Pure arithmetic with no database access, so every rule here is unit tested
 * without booting Laravel. EntitlementService builds one of these per user and
 * supplies the usage counts the methods below compare against.
 *
 * Every limit is applied when something new is about to be created (a
 * connection, a generated post, an image). Nothing here is consulted at publish
 * time, so a post that is already scheduled always goes out.
 */
final class Entitlements
{
    public const PLAN_NONE = 'none';

    // Reasons a request is refused, returned to the frontend as `error`.
    public const LIMIT_PLATFORM_NOT_INCLUDED = 'platform_not_included';
    public const LIMIT_PLATFORM_COUNT = 'platform_limit';
    public const LIMIT_POSTS_PER_WEEK = 'posts_per_week_limit';
    public const LIMIT_LOCATIONS = 'location_limit';
    public const LIMIT_EXTRA_LOCATION = 'extra_location_required';

    public const IMAGE_PERIOD_MONTH = 'month';
    public const IMAGE_PERIOD_TRIAL = 'trial';

    /**
     * @param  string[]  $platforms  platforms the plan may use at all
     * @param  array<string, int>  $postsPerWeekByPlatform  per-platform overrides of $postsPerWeek
     */
    public function __construct(
        public readonly string $plan,
        public readonly string $planName,
        public readonly bool $active,
        public readonly bool $onTrial,
        public readonly int $locations,
        public readonly bool $extraLocationsAllowed,
        public readonly ?int $platformLimit,
        public readonly array $platforms,
        public readonly int $postsPerWeek,
        public readonly array $postsPerWeekByPlatform,
        public readonly int $aiImages,
        public readonly string $imagePeriod,
        public readonly ?int $twitterPostCap,
        public readonly int $analyticsDays,
    ) {}

    /**
     * Entitlements for a plan definition from config/plans.php.
     *
     * A trial runs on the trial plan's limits, with X posts and AI images
     * capped in total for the whole trial rather than per month.
     */
    public static function fromPlan(string $key, array $plan, bool $onTrial = false, array $trialCaps = []): self
    {
        $images = (int) ($plan['ai_images_per_month'] ?? 0);
        $twitterCap = null;

        if ($onTrial) {
            if (isset($trialCaps['ai_images'])) {
                $images = min($images, (int) $trialCaps['ai_images']);
            }
            if (isset($trialCaps['twitter_posts'])) {
                $twitterCap = (int) $trialCaps['twitter_posts'];
            }
        }

        return new self(
            plan: $key,
            planName: (string) ($plan['name'] ?? ucfirst($key)),
            active: true,
            onTrial: $onTrial,
            locations: (int) ($plan['locations'] ?? 1),
            extraLocationsAllowed: (bool) ($plan['extra_locations'] ?? false),
            platformLimit: isset($plan['platform_limit']) ? (int) $plan['platform_limit'] : null,
            platforms: array_values($plan['platforms'] ?? []),
            postsPerWeek: (int) ($plan['posts_per_week'] ?? 0),
            postsPerWeekByPlatform: $plan['posts_per_week_by_platform'] ?? [],
            aiImages: $images,
            imagePeriod: $onTrial ? self::IMAGE_PERIOD_TRIAL : self::IMAGE_PERIOD_MONTH,
            twitterPostCap: $twitterCap,
            analyticsDays: (int) ($plan['analytics_days'] ?? 30),
        );
    }

    /**
     * No plan: the trial has ended, or a subscription lapsed.
     *
     * Nothing is generated and nothing new is published, but connecting
     * platforms stays open, so someone choosing a plan after their trial can
     * set things up without hitting a wall first. Nothing is deleted.
     *
     * @param  string[]  $platforms
     */
    public static function none(array $platforms): self
    {
        return new self(
            plan: self::PLAN_NONE,
            planName: 'No plan',
            active: false,
            onTrial: false,
            locations: 1,
            extraLocationsAllowed: false,
            platformLimit: null,
            platforms: array_values($platforms),
            postsPerWeek: 0,
            postsPerWeekByPlatform: [],
            aiImages: 0,
            imagePeriod: self::IMAGE_PERIOD_MONTH,
            twitterPostCap: null,
            analyticsDays: 30,
        );
    }

    public function allowsPlatform(string $platform): bool
    {
        return in_array($platform, $this->platforms, true);
    }

    /**
     * Why connecting $platform would break the plan, or null if it is fine.
     *
     * Reconnecting a platform that is already connected is always allowed:
     * refusing it would strand posts that are already scheduled for it.
     *
     * @param  string[]  $connected  platforms the business already has connected
     */
    public function connectBlockReason(string $platform, array $connected): ?string
    {
        if (in_array($platform, $connected, true) || ! $this->active) {
            return null;
        }

        if (! $this->allowsPlatform($platform)) {
            return self::LIMIT_PLATFORM_NOT_INCLUDED;
        }

        if ($this->platformLimit !== null && count(array_unique($connected)) >= $this->platformLimit) {
            return self::LIMIT_PLATFORM_COUNT;
        }

        return null;
    }

    /**
     * The connected platforms generation may write for, in connection order.
     *
     * Someone who connected four platforms on the trial and then chose Local
     * keeps all four connections, but only the first two the plan allows are
     * written for. They swap which two by disconnecting one; nothing is
     * deleted and the others simply pause.
     *
     * @param  string[]  $connectedInOrder  oldest connection first
     * @return string[]
     */
    public function usablePlatforms(array $connectedInOrder): array
    {
        if (! $this->active) {
            return [];
        }

        $usable = array_values(array_filter(
            array_unique($connectedInOrder),
            fn (string $platform) => $this->allowsPlatform($platform)
        ));

        return $this->platformLimit === null
            ? $usable
            : array_slice($usable, 0, $this->platformLimit);
    }

    /** Most posts a week the plan allows on one platform. */
    public function postsPerWeekCap(string $platform): int
    {
        if (! $this->active || ! $this->allowsPlatform($platform)) {
            return 0;
        }

        return (int) ($this->postsPerWeekByPlatform[$platform] ?? $this->postsPerWeek);
    }

    /**
     * A business's chosen cadence, held to what the plan allows. A setting
     * saved on a bigger plan is kept as it is, so upgrading again restores it,
     * but only the capped number is generated in the meantime.
     */
    public function clampPostsPerWeek(string $platform, int $requested): int
    {
        return max(0, min($requested, $this->postsPerWeekCap($platform)));
    }

    public function aiImagesRemaining(int $used): int
    {
        return max(0, $this->aiImages - $used);
    }

    /** Null when X is not capped in total, which is everywhere but the trial. */
    public function twitterPostsRemaining(int $used): ?int
    {
        return $this->twitterPostCap === null ? null : max(0, $this->twitterPostCap - $used);
    }

    /** Locations the account may hold, including any paid extra ones. */
    public function locationLimit(int $extraLocations = 0): int
    {
        return $this->locations + ($this->extraLocationsAllowed ? max(0, $extraLocations) : 0);
    }

    /**
     * Whether another location can be added, and if not, why.
     *
     * Returns null when it can be added as things stand, LIMIT_EXTRA_LOCATION
     * when it can be added for the add-on price, or LIMIT_LOCATIONS when the
     * plan needs to change first.
     */
    public function addLocationBlockReason(int $existingLocations, int $extraLocations = 0): ?string
    {
        if ($existingLocations < $this->locationLimit($extraLocations)) {
            return null;
        }

        return $this->active && $this->extraLocationsAllowed
            ? self::LIMIT_EXTRA_LOCATION
            : self::LIMIT_LOCATIONS;
    }

    /** Earliest date analytics may reach back to. */
    public function analyticsEarliest(CarbonInterface $now): CarbonInterface
    {
        return $now->copy()->subDays($this->analyticsDays)->startOfDay();
    }

    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'plan_name' => $this->planName,
            'active' => $this->active,
            'on_trial' => $this->onTrial,
            'locations' => $this->locations,
            'extra_locations_allowed' => $this->extraLocationsAllowed,
            'platform_limit' => $this->platformLimit,
            'platforms' => $this->platforms,
            'posts_per_week' => $this->postsPerWeek,
            'posts_per_week_by_platform' => (object) $this->postsPerWeekByPlatform,
            'ai_images' => $this->aiImages,
            'ai_images_period' => $this->imagePeriod,
            'twitter_post_cap' => $this->twitterPostCap,
            'analytics_days' => $this->analyticsDays,
        ];
    }
}
