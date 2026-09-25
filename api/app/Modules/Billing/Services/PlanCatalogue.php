<?php

namespace App\Modules\Billing\Services;

/**
 * Read-only view over config/plans.php: what postd sells and at what price.
 *
 * Built from a plain array rather than reading config() itself, so the unit
 * tests can hand it a catalogue without booting Laravel. EntitlementService
 * turns what is in here into what a particular user may do.
 */
class PlanCatalogue
{
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_ANNUAL = 'annual';

    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('plans', []));
    }

    /** @return array<string, array> every plan, legacy ones included */
    public function all(): array
    {
        return $this->config['plans'] ?? [];
    }

    /** @return array<string, array> the plans a new customer can choose */
    public function offered(): array
    {
        return array_filter($this->all(), fn (array $plan) => $plan['offered'] ?? false);
    }

    /**
     * Resolve a stored plan string to its current key. 'starter' became
     * 'local', and anything unrecognised comes back null rather than being
     * guessed at.
     */
    public function normalise(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $key = $this->config['aliases'][$key] ?? $key;

        return isset($this->all()[$key]) ? $key : null;
    }

    public function get(?string $key): ?array
    {
        $key = $this->normalise($key);

        return $key ? $this->all()[$key] : null;
    }

    public function name(?string $key): string
    {
        return $this->get($key)['name'] ?? ucfirst((string) $key);
    }

    public function trialPlan(): string
    {
        return $this->config['trial_plan'] ?? 'growth';
    }

    public function trialCaps(): array
    {
        return $this->config['trial_caps'] ?? [];
    }

    /**
     * The Stripe price for a plan and interval, or null when it has not been
     * set up. Callers treat null as "not available" rather than an error.
     */
    public function priceId(string $key, string $interval = self::INTERVAL_MONTHLY): ?string
    {
        $plan = $this->get($key);

        return ($plan[$interval]['stripe_price_id'] ?? null) ?: null;
    }

    public function pricePence(string $key, string $interval = self::INTERVAL_MONTHLY): ?int
    {
        $plan = $this->get($key);
        $price = $plan[$interval]['price'] ?? null;

        return $price === null ? null : (int) $price;
    }

    public function extraLocationPriceId(): ?string
    {
        return ($this->config['extra_location']['stripe_price_id'] ?? null) ?: null;
    }

    public function extraLocationPricePence(): int
    {
        return (int) ($this->config['extra_location']['price'] ?? 0);
    }

    /**
     * Map a Stripe price back to its plan and billing interval.
     *
     * @return array{plan: string, interval: string}|null
     */
    public function lookupPrice(?string $priceId): ?array
    {
        if (! $priceId) {
            return null;
        }

        foreach ($this->all() as $key => $plan) {
            foreach ([self::INTERVAL_MONTHLY, self::INTERVAL_ANNUAL] as $interval) {
                if (($plan[$interval]['stripe_price_id'] ?? null) === $priceId) {
                    return ['plan' => $key, 'interval' => $interval];
                }
            }
        }

        return null;
    }

    /**
     * What a Stripe price is worth per month, for MRR reporting. An annual
     * price counts as a twelfth of itself.
     */
    public function monthlyValuePence(?string $priceId): int
    {
        $match = $this->lookupPrice($priceId);
        if (! $match) {
            return 0;
        }

        $price = (int) $this->pricePence($match['plan'], $match['interval']);

        return $match['interval'] === self::INTERVAL_ANNUAL ? intdiv($price, 12) : $price;
    }
}
