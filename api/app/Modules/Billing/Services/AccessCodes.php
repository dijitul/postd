<?php

namespace App\Modules\Billing\Services;

/**
 * Codes that give an account a plan for free: dijitul's retainer clients,
 * who already pay dijitul directly.
 *
 * Codes live in PROMO_CODES on the server ("CODE:plan,OTHER:plan"), never in
 * git, so a leaked code can be changed without a deploy. Matching ignores case
 * and surrounding spaces.
 */
class AccessCodes
{
    public static function planFor(?string $raw): ?string
    {
        $code = strtoupper(trim((string) $raw));
        if ($code === '') {
            return null;
        }

        foreach (explode(',', (string) config('plans.access_codes', '')) as $entry) {
            [$c, $plan] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, null);
            if ($c !== '' && strtoupper($c) === $code && $plan && array_key_exists($plan, (array) config('plans.plans'))) {
                return $plan;
            }
        }

        return null;
    }
}
