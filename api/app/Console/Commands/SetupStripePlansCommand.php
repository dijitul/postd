<?php

namespace App\Console\Commands;

use App\Modules\Billing\Services\PlanCatalogue;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

/**
 * Creates the Stripe products, prices and VAT rate that config/plans.php expects.
 *
 * Doing this by hand in the dashboard means five prices, two products and a
 * tax rate, each with an amount, currency and interval that has to match
 * config/plans.php exactly. One typo and a customer is charged the wrong
 * amount. This reads the amounts from the config instead, tags everything
 * with a lookup key so a second run finds what the first one made rather than
 * duplicating it, and prints the .env lines to paste.
 *
 * Existing Starter and Growth prices are reused, never replaced, so current
 * subscribers are untouched. Run it in test mode first by pointing
 * STRIPE_SECRET at a test key.
 */
class SetupStripePlansCommand extends Command
{
    protected $signature = 'billing:setup-stripe
        {--dry-run : Show what would be created without creating anything}';

    protected $description = 'Create the Stripe products, prices and UK VAT rate for the current plans';

    private StripeClient $stripe;

    private bool $dryRun;

    /** @var array<string, string> env name => value to print at the end */
    private array $env = [];

    public function handle(PlanCatalogue $catalogue): int
    {
        $secret = (string) config('cashier.secret');

        if ($secret === '') {
            $this->error('STRIPE_SECRET is not set.');
            return Command::FAILURE;
        }

        $this->stripe = Cashier::stripe();
        $this->dryRun = (bool) $this->option('dry-run');

        $mode = str_starts_with($secret, 'sk_live') || str_starts_with($secret, 'rk_live') ? 'LIVE' : 'TEST';
        $this->warn("Stripe {$mode} mode".($this->dryRun ? ' (dry run, nothing will be created)' : ''));
        $this->newLine();

        $this->ensureVatRate();

        // Local and Growth keep the products their current prices belong to, so
        // the annual price sits alongside the monthly one a subscriber already has.
        foreach (['local' => 'STRIPE_PLAN_LOCAL', 'growth' => 'STRIPE_PLAN_GROWTH'] as $plan => $monthlyEnv) {
            $monthlyId = $catalogue->priceId($plan, PlanCatalogue::INTERVAL_MONTHLY);
            $productId = $monthlyId ? $this->checkExistingPrice($plan, $monthlyId, $catalogue) : null;

            $this->ensurePrice(
                lookupKey: "postd_{$plan}_annual",
                env: strtoupper("STRIPE_PLAN_{$plan}_ANNUAL"),
                configured: $catalogue->priceId($plan, PlanCatalogue::INTERVAL_ANNUAL),
                pence: $this->pence($catalogue, $plan, PlanCatalogue::INTERVAL_ANNUAL),
                interval: 'year',
                productId: $productId,
                productName: 'postd.uk '.$catalogue->name($plan),
                plan: $plan,
            );
        }

        // Agency is new, so its monthly price creates the product and the annual
        // price joins it.
        $agencyProduct = $this->ensurePrice(
            lookupKey: 'postd_agency_monthly',
            env: 'STRIPE_PLAN_AGENCY',
            configured: $catalogue->priceId('agency', PlanCatalogue::INTERVAL_MONTHLY),
            pence: $this->pence($catalogue, 'agency', PlanCatalogue::INTERVAL_MONTHLY),
            interval: 'month',
            productId: null,
            productName: 'postd.uk Agency',
            plan: 'agency',
        );

        $this->ensurePrice(
            lookupKey: 'postd_agency_annual',
            env: 'STRIPE_PLAN_AGENCY_ANNUAL',
            configured: $catalogue->priceId('agency', PlanCatalogue::INTERVAL_ANNUAL),
            pence: $this->pence($catalogue, 'agency', PlanCatalogue::INTERVAL_ANNUAL),
            interval: 'year',
            productId: $agencyProduct,
            productName: 'postd.uk Agency',
            plan: 'agency',
        );

        $this->ensurePrice(
            lookupKey: 'postd_extra_location_monthly',
            env: 'STRIPE_PRICE_EXTRA_LOCATION',
            configured: $catalogue->extraLocationPriceId(),
            pence: $catalogue->extraLocationPricePence(),
            interval: 'month',
            productId: null,
            productName: 'postd.uk extra location',
            plan: 'extra_location',
        );

        $this->newLine();

        if ($this->env === []) {
            $this->info('Everything is already set up. Nothing to add to .env.');
            return Command::SUCCESS;
        }

        $this->info($this->dryRun ? 'A real run would print these .env lines:' : 'Add these lines to .env, then run php artisan config:clear:');
        $this->newLine();

        foreach ($this->env as $name => $value) {
            $this->line("{$name}={$value}");
        }

        return Command::SUCCESS;
    }

    /**
     * A 20% UK VAT rate, added on top of the price, for Cashier to attach to
     * every new subscription (see User::taxRates()).
     */
    private function ensureVatRate(): void
    {
        $configured = config('plans.vat_tax_rate_id');

        if ($configured) {
            $rate = $this->stripe->taxRates->retrieve($configured);
            $this->line("VAT rate: using {$rate->id} ({$rate->percentage}%, ".($rate->inclusive ? 'inclusive' : 'exclusive').')');

            if ($rate->inclusive || (float) $rate->percentage !== 20.0) {
                $this->warn('  That rate is not 20% exclusive. Prices on the site are shown plus VAT, so check it.');
            }

            return;
        }

        foreach ($this->stripe->taxRates->all(['active' => true, 'limit' => 100])->autoPagingIterator() as $rate) {
            if (($rate->metadata['postd_key'] ?? null) === 'uk_vat_20') {
                $this->line("VAT rate: found {$rate->id}");
                $this->env['STRIPE_TAX_RATE_VAT'] = $rate->id;
                return;
            }
        }

        if ($this->dryRun) {
            $this->line('VAT rate: would create UK VAT 20%, exclusive');
            $this->env['STRIPE_TAX_RATE_VAT'] = 'txr_...';
            return;
        }

        $rate = $this->stripe->taxRates->create([
            'display_name' => 'VAT',
            'description' => 'UK VAT 20%',
            'jurisdiction' => 'United Kingdom',
            'country' => 'GB',
            'percentage' => 20,
            'inclusive' => false,
            'tax_type' => 'vat',
            'metadata' => ['postd_key' => 'uk_vat_20'],
        ]);

        $this->line("VAT rate: created {$rate->id}");
        $this->env['STRIPE_TAX_RATE_VAT'] = $rate->id;
    }

    /**
     * Warn if a price we are reusing does not match the config, and return its product.
     */
    private function checkExistingPrice(string $plan, string $priceId, PlanCatalogue $catalogue): string
    {
        $price = $this->stripe->prices->retrieve($priceId);
        $expected = $this->pence($catalogue, $plan, PlanCatalogue::INTERVAL_MONTHLY);

        $this->line(sprintf('%s monthly: reusing %s (£%s/%s)', ucfirst($plan), $price->id, number_format($price->unit_amount / 100, 2), $price->recurring->interval ?? '?'));

        if ($price->unit_amount !== $expected || $price->currency !== 'gbp' || ($price->recurring->interval ?? null) !== 'month') {
            $this->warn(sprintf('  config/plans.php expects £%s a month in GBP. Check this price before selling.', number_format($expected / 100, 2)));
        }

        return is_string($price->product) ? $price->product : $price->product->id;
    }

    /**
     * Find or create one recurring GBP price, returning the product it belongs to.
     */
    private function ensurePrice(
        string $lookupKey,
        string $env,
        ?string $configured,
        int $pence,
        string $interval,
        ?string $productId,
        string $productName,
        string $plan,
    ): ?string {
        $label = str_pad($env, 28);
        $amount = '£'.number_format($pence / 100, 2).'/'.$interval;

        if ($configured) {
            $price = $this->stripe->prices->retrieve($configured);
            $this->line("{$label} already set ({$configured})");

            if ($price->unit_amount !== $pence) {
                $this->warn("  That price is £".number_format($price->unit_amount / 100, 2).", config expects {$amount}.");
            }

            return is_string($price->product) ? $price->product : $price->product->id;
        }

        $existing = $this->stripe->prices->all(['lookup_keys' => [$lookupKey], 'limit' => 1])->data[0] ?? null;

        if ($existing) {
            $this->line("{$label} found {$existing->id} by lookup key");
            $this->env[$env] = $existing->id;

            return is_string($existing->product) ? $existing->product : $existing->product->id;
        }

        if ($this->dryRun) {
            $this->line("{$label} would create {$amount}".($productId ? " on {$productId}" : " with a new product \"{$productName}\""));
            $this->env[$env] = 'price_...';

            return $productId;
        }

        $params = [
            'currency' => 'gbp',
            'unit_amount' => $pence,
            'recurring' => ['interval' => $interval],
            // VAT is added on top by the tax rate, matching "+ VAT" on the site.
            'tax_behavior' => 'exclusive',
            'lookup_key' => $lookupKey,
            'metadata' => ['plan' => $plan],
        ];

        if ($productId) {
            $params['product'] = $productId;
        } else {
            $params['product_data'] = ['name' => $productName, 'metadata' => ['plan' => $plan]];
        }

        $price = $this->stripe->prices->create($params);
        $this->line("{$label} created {$price->id} ({$amount})");
        $this->env[$env] = $price->id;

        return is_string($price->product) ? $price->product : $price->product->id;
    }

    private function pence(PlanCatalogue $catalogue, string $plan, string $interval): int
    {
        return (int) ($catalogue->get($plan)[$interval]['price'] ?? 0);
    }
}
