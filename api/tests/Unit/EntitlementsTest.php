<?php

namespace Tests\Unit;

use App\Modules\Billing\Services\Entitlements;
use App\Modules\Billing\Services\EntitlementService;
use App\Modules\Billing\Services\PlanCatalogue;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The plan rules, tested against the real config/plans.php with no database.
 */
class EntitlementsTest extends TestCase
{
    private const ALL = ['google_business_profile', 'facebook', 'linkedin', 'twitter'];

    private function config(array $prices = []): array
    {
        $config = require __DIR__.'/../../config/plans.php';

        // Stripe price IDs come from env, which is empty here. Fill in the ones
        // a test needs so price lookups have something to match.
        foreach ($prices as $path => $id) {
            [$plan, $interval] = explode('.', $path);
            $config['plans'][$plan][$interval]['stripe_price_id'] = $id;
        }

        return $config;
    }

    private function service(array $prices = []): EntitlementService
    {
        return new EntitlementService(new PlanCatalogue($this->config($prices)));
    }

    // ── Platforms ──────────────────────────────────────────────────────────

    public function test_local_allows_any_two_platforms_but_not_x(): void
    {
        $local = $this->service()->resolve('local');

        $this->assertNull($local->connectBlockReason('facebook', []));
        $this->assertNull($local->connectBlockReason('linkedin', ['facebook']));
        $this->assertSame(Entitlements::LIMIT_PLATFORM_COUNT, $local->connectBlockReason('google_business_profile', ['facebook', 'linkedin']));
        $this->assertSame(Entitlements::LIMIT_PLATFORM_NOT_INCLUDED, $local->connectBlockReason('twitter', []));
    }

    public function test_reconnecting_an_existing_platform_is_never_blocked(): void
    {
        $local = $this->service()->resolve('local');

        // Even over the limit, as a trial that became Local would be.
        $this->assertNull($local->connectBlockReason('google_business_profile', self::ALL));
        $this->assertNull($local->connectBlockReason('twitter', ['twitter', 'facebook']));
    }

    public function test_growth_and_agency_allow_every_platform(): void
    {
        foreach (['growth', 'agency', 'pro'] as $plan) {
            $entitlements = $this->service()->resolve($plan);

            foreach (self::ALL as $platform) {
                $this->assertNull($entitlements->connectBlockReason($platform, array_diff(self::ALL, [$platform])), "{$plan} {$platform}");
            }
        }
    }

    public function test_local_writes_for_the_two_oldest_allowed_connections(): void
    {
        $local = $this->service()->resolve('local');

        // X is skipped outright, then the first two that remain are kept.
        $this->assertSame(
            ['facebook', 'google_business_profile'],
            $local->usablePlatforms(['twitter', 'facebook', 'google_business_profile', 'linkedin'])
        );
    }

    public function test_no_plan_writes_for_nothing_but_can_still_connect(): void
    {
        $none = $this->service()->resolve('none');

        $this->assertFalse($none->active);
        $this->assertSame([], $none->usablePlatforms(self::ALL));
        $this->assertNull($none->connectBlockReason('twitter', ['facebook', 'linkedin', 'google_business_profile']));
        $this->assertSame(0, $none->postsPerWeekCap('facebook'));
    }

    // ── Cadence ────────────────────────────────────────────────────────────

    public function test_posts_per_week_caps_by_plan(): void
    {
        $service = $this->service();

        $this->assertSame(3, $service->resolve('local')->postsPerWeekCap('facebook'));
        $this->assertSame(0, $service->resolve('local')->postsPerWeekCap('twitter'));
        $this->assertSame(7, $service->resolve('growth')->postsPerWeekCap('twitter'));
        $this->assertSame(14, $service->resolve('agency')->postsPerWeekCap('linkedin'));
        $this->assertSame(7, $service->resolve('agency')->postsPerWeekCap('google_business_profile'));
    }

    public function test_clamp_holds_a_saved_cadence_to_the_plan(): void
    {
        $local = $this->service()->resolve('local');

        $this->assertSame(3, $local->clampPostsPerWeek('facebook', 7));
        $this->assertSame(2, $local->clampPostsPerWeek('facebook', 2));
        $this->assertSame(0, $local->clampPostsPerWeek('facebook', -1));
    }

    // ── Images and trial caps ──────────────────────────────────────────────

    public function test_monthly_ai_image_allowances(): void
    {
        $service = $this->service();

        $this->assertSame(5, $service->resolve('local')->aiImages);
        $this->assertSame(30, $service->resolve('growth')->aiImages);
        $this->assertSame(100, $service->resolve('agency')->aiImages);
        $this->assertSame(0, $service->resolve('local')->aiImagesRemaining(7));
        $this->assertSame(25, $service->resolve('growth')->aiImagesRemaining(5));
    }

    public function test_trial_runs_on_growth_with_x_and_images_capped_in_total(): void
    {
        $trial = $this->service()->resolve('growth', onTrial: true);

        $this->assertTrue($trial->onTrial);
        $this->assertTrue($trial->allowsPlatform('twitter'));
        $this->assertSame(10, $trial->aiImages);
        $this->assertSame(Entitlements::IMAGE_PERIOD_TRIAL, $trial->imagePeriod);
        $this->assertSame(10, $trial->twitterPostCap);
        $this->assertSame(3, $trial->twitterPostsRemaining(7));
        $this->assertSame(0, $trial->twitterPostsRemaining(12));
    }

    public function test_paid_plans_do_not_cap_x_in_total(): void
    {
        $this->assertNull($this->service()->resolve('growth')->twitterPostsRemaining(500));
    }

    // ── Locations ──────────────────────────────────────────────────────────

    public function test_location_limits(): void
    {
        $service = $this->service();

        $this->assertSame(Entitlements::LIMIT_LOCATIONS, $service->resolve('growth')->addLocationBlockReason(1));
        $this->assertNull($service->resolve('agency')->addLocationBlockReason(2));
        $this->assertSame(Entitlements::LIMIT_EXTRA_LOCATION, $service->resolve('agency')->addLocationBlockReason(3));
        // Two paid extras make room for a fifth, not a sixth.
        $this->assertNull($service->resolve('agency')->addLocationBlockReason(4, 2));
        $this->assertSame(Entitlements::LIMIT_EXTRA_LOCATION, $service->resolve('agency')->addLocationBlockReason(5, 2));
        // Extras bought on Agency do not count on a plan without them.
        $this->assertSame(1, $service->resolve('growth')->locationLimit(2));
    }

    public function test_legacy_pro_gets_agency_entitlements(): void
    {
        $service = $this->service();
        $pro = $service->resolve('pro');
        $agency = $service->resolve('agency');

        $this->assertSame('Pro', $pro->planName);
        $this->assertSame($agency->locations, $pro->locations);
        $this->assertSame($agency->platforms, $pro->platforms);
        $this->assertSame($agency->aiImages, $pro->aiImages);
        $this->assertSame(14, $pro->postsPerWeekCap('facebook'));
    }

    // ── Analytics ──────────────────────────────────────────────────────────

    public function test_analytics_history_by_plan(): void
    {
        $now = Carbon::parse('2026-09-25 15:00:00');

        $this->assertSame('2026-08-26', $this->service()->resolve('local')->analyticsEarliest($now)->toDateString());
        $this->assertSame(365, $this->service()->resolve('growth')->analyticsDays);
    }

    // ── Catalogue ──────────────────────────────────────────────────────────

    public function test_starter_is_treated_as_local(): void
    {
        $catalogue = new PlanCatalogue($this->config());

        $this->assertSame('local', $catalogue->normalise('starter'));
        $this->assertSame('local', $this->service()->resolve('starter')->plan);
        $this->assertNull($catalogue->normalise('platinum'));
        $this->assertFalse($this->service()->resolve('platinum')->active);
    }

    public function test_prices_map_back_to_plan_and_interval(): void
    {
        $catalogue = new PlanCatalogue($this->config([
            'local.monthly' => 'price_local_m',
            'growth.annual' => 'price_growth_y',
            'pro.monthly' => 'price_pro_m',
        ]));

        $this->assertSame(['plan' => 'local', 'interval' => 'monthly'], $catalogue->lookupPrice('price_local_m'));
        $this->assertSame(['plan' => 'growth', 'interval' => 'annual'], $catalogue->lookupPrice('price_growth_y'));
        $this->assertSame(['plan' => 'pro', 'interval' => 'monthly'], $catalogue->lookupPrice('price_pro_m'));
        $this->assertNull($catalogue->lookupPrice('price_unknown'));

        // Annual counts as a twelfth for MRR: £390 a year is £32.50 a month.
        $this->assertSame(3250, $catalogue->monthlyValuePence('price_growth_y'));
        $this->assertSame(6900, $catalogue->monthlyValuePence('price_pro_m'));
    }

    public function test_unset_prices_are_unavailable_not_errors(): void
    {
        $catalogue = new PlanCatalogue($this->config());

        $this->assertNull($catalogue->priceId('agency', PlanCatalogue::INTERVAL_ANNUAL));
        $this->assertNull($catalogue->extraLocationPriceId());
    }

    public function test_pro_is_not_offered_to_new_customers(): void
    {
        $offered = array_keys((new PlanCatalogue($this->config()))->offered());

        $this->assertSame(['local', 'growth', 'agency'], $offered);
    }

    public function test_annual_is_two_months_free(): void
    {
        $catalogue = new PlanCatalogue($this->config());

        foreach (['local', 'growth', 'agency'] as $plan) {
            $this->assertSame($catalogue->pricePence($plan) * 10, $catalogue->pricePence($plan, PlanCatalogue::INTERVAL_ANNUAL), $plan);
        }
    }

    // ── Messages ───────────────────────────────────────────────────────────

    public function test_limit_messages_explain_and_point_at_the_upgrade(): void
    {
        $service = $this->service();
        $local = $service->resolve('local');

        $x = $service->limitResponse(Entitlements::LIMIT_PLATFORM_NOT_INCLUDED, $local, ['platform' => 'twitter']);
        $this->assertSame('platform_not_included', $x['error']);
        $this->assertSame('growth', $x['upgrade_plan']);
        $this->assertSame('/billing', $x['upgrade_url']);
        $this->assertStringContainsString('£39 a month', $x['message']);

        $cadence = $service->limitResponse(Entitlements::LIMIT_POSTS_PER_WEEK, $local, ['platform' => 'facebook']);
        $this->assertStringContainsString('up to 3 posts a week', $cadence['message']);
        $this->assertStringContainsString('raises that to 7', $cadence['message']);

        $location = $service->limitResponse(Entitlements::LIMIT_LOCATIONS, $service->resolve('growth'));
        $this->assertSame('agency', $location['upgrade_plan']);
        $this->assertStringContainsString('£79 a month', $location['message']);

        foreach ([$x, $cadence, $location] as $response) {
            $this->assertStringNotContainsString("\u{2014}", $response['message'], 'no em-dashes in copy');
        }
    }
}
