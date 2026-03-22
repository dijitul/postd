<?php

namespace App\Modules\Billing\Services;

use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionService
{
    /**
     * Get all available plans with their features.
     */
    public function getPlans(): array
    {
        $cashierPlans = config('cashier.plans', []);

        return collect($cashierPlans)
            ->filter(fn ($plan, $key) => $key !== 'tiktok_addon')
            ->map(fn ($plan, $key) => [
                'id' => $key,
                'name' => $plan['name'],
                'price' => $plan['price'],
                'price_display' => '£'.number_format($plan['price'] / 100, 0).'/mo',
                'price_ex_vat' => $plan['price'],
                'platform_limit' => $plan['platform_limit'],
                'tiktok_included' => $plan['tiktok_included'],
                'gbp_included' => $plan['gbp_included'],
                'stripe_price_id' => $plan['stripe_price_id'],
                'is_popular' => $key === 'growth',
                'features' => $this->getPlanFeatures($key),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Subscribe a user to a plan.
     */
    public function subscribe(User $user, string $planName, string $paymentMethodId): array
    {
        $plan = config("cashier.plans.{$planName}");
        if (! $plan) {
            throw new \InvalidArgumentException("Unknown plan: {$planName}");
        }

        // Create Stripe customer if needed
        if (! $user->stripe_id) {
            $user->createAsStripeCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'metadata' => ['user_id' => $user->id],
                'tax' => ['ip_address' => request()->ip()],
            ]);
        }

        // Attach the payment method
        $user->updateDefaultPaymentMethod($paymentMethodId);

        try {
            $subscription = $user->newSubscription('default', $plan['stripe_price_id'])
                ->withMetadata(['plan' => $planName, 'user_id' => $user->id])
                ->create($paymentMethodId, [
                    'automatic_tax' => ['enabled' => true], // Stripe Tax for UK VAT
                ]);

            Log::info("SubscriptionService: User {$user->id} subscribed to {$planName}");

            return [
                'plan' => $planName,
                'status' => $subscription->stripe_status,
                'stripe_subscription_id' => $subscription->stripe_id,
            ];
        } catch (IncompletePayment $e) {
            throw new \RuntimeException(
                'Payment requires additional action. Please complete payment in the Stripe dashboard.',
                0,
                $e
            );
        }
    }

    /**
     * Get the feature list for a given plan.
     */
    private function getPlanFeatures(string $plan): array
    {
        $features = [
            'starter' => [
                '2 social platforms',
                'Google Business Profile (free)',
                'AI-generated posts',
                'Post approval inbox',
                'Basic analytics',
            ],
            'growth' => [
                '4 social platforms',
                'Google Business Profile (free)',
                'AI-generated posts with images',
                'Post approval inbox',
                'Full analytics',
                'Local news hooks',
                'TikTok add-on available (+£15/mo)',
            ],
            'pro' => [
                'All platforms (unlimited)',
                'Google Business Profile (free)',
                'AI-generated posts with images',
                'TikTok video generation included',
                'Full analytics',
                'Local news hooks',
                'Priority support',
                'White-label ready',
            ],
        ];

        return $features[$plan] ?? [];
    }
}
