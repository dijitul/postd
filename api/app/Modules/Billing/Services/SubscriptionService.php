<?php

namespace App\Modules\Billing\Services;

use App\Models\Post;
use App\Models\User;
use App\Modules\Schedule\Services\SchedulingService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionService
{
    public function __construct(
        private readonly PlanCatalogue $catalogue,
        private readonly EntitlementService $entitlements,
        private readonly SchedulingService $scheduling
    ) {}

    /**
     * The plans shown on the Billing page, straight from config/plans.php.
     *
     * A legacy plan (Pro) is included only for the customer already on it, so
     * they can see what they have. An interval whose Stripe price is not set
     * up yet comes back unavailable, and the page hides it.
     */
    public function getPlans(?string $currentPlan = null): array
    {
        $plans = $this->catalogue->offered();

        $current = $this->catalogue->normalise($currentPlan);
        if ($current && ! isset($plans[$current])) {
            $plans[$current] = $this->catalogue->get($current);
        }

        return collect($plans)
            ->map(fn (array $plan, string $key) => [
                'id' => $key,
                'name' => $plan['name'],
                'tagline' => $plan['tagline'] ?? null,
                'offered' => (bool) ($plan['offered'] ?? false),
                'is_popular' => (bool) ($plan['popular'] ?? false),
                'monthly' => [
                    'price' => $plan['monthly']['price'] ?? null,
                    'available' => (bool) $this->catalogue->priceId($key, PlanCatalogue::INTERVAL_MONTHLY),
                ],
                'annual' => [
                    'price' => $plan['annual']['price'] ?? null,
                    'available' => (bool) $this->catalogue->priceId($key, PlanCatalogue::INTERVAL_ANNUAL),
                ],
                'limits' => [
                    'locations' => $plan['locations'],
                    'extra_locations' => $plan['extra_locations'],
                    'platform_limit' => $plan['platform_limit'],
                    'platforms' => $plan['platforms'],
                    'posts_per_week' => $plan['posts_per_week'],
                    'posts_per_week_by_platform' => (object) $plan['posts_per_week_by_platform'],
                    'ai_images_per_month' => $plan['ai_images_per_month'],
                    'analytics_days' => $plan['analytics_days'],
                ],
            ])
            ->values()
            ->toArray();
    }

    public function extraLocationOffer(): array
    {
        return [
            'price' => $this->catalogue->extraLocationPricePence(),
            'available' => (bool) $this->catalogue->extraLocationPriceId(),
        ];
    }

    /**
     * Start a Stripe Checkout session for a new subscription.
     *
     * Checkout rather than an in-app card form: it needs no Stripe.js on the
     * frontend and handles SCA. Anyone still inside their trial keeps the rest
     * of it, as the subscription's first charge waits until the trial ends.
     *
     * @return string the Checkout URL to send the browser to
     */
    public function checkoutUrl(User $user, string $plan, string $interval, string $successUrl, string $cancelUrl): string
    {
        $priceId = $this->requirePrice($plan, $interval);

        $builder = $user->newSubscription('default', $priceId)
            ->withMetadata(['plan' => $plan, 'interval' => $interval, 'user_id' => $user->id]);

        // Stripe will not accept a trial end less than 48 hours away.
        if ($user->trial_ends_at && $user->trial_ends_at->isAfter(now()->addDays(2))) {
            $builder->trialUntil($user->trial_ends_at);
        }

        $sessionOptions = [
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Customers are businesses, and most will want their company name
            // and VAT number on the invoice to reclaim the VAT we charge.
            'tax_id_collection' => ['enabled' => true],
            'billing_address_collection' => 'required',
        ];

        // Stripe refuses tax ID collection for an existing customer unless it
        // may save the name and address entered on the Checkout page.
        if ($user->stripe_id) {
            $sessionOptions['customer_update'] = ['name' => 'auto', 'address' => 'auto'];
        }

        $checkout = $builder->checkout($sessionOptions, [
            'name' => $user->name,
            'email' => $user->email,
            'metadata' => ['user_id' => $user->id],
        ]);

        return $checkout->asStripeCheckoutSession()->url;
    }

    /**
     * Move an existing subscriber to a different plan or interval.
     *
     * Agency extra locations are carried across when the new plan allows them.
     * A move that would leave more locations than the new plan covers is
     * refused with an explanation, never applied by switching locations off.
     */
    public function changePlan(User $user, string $plan, string $interval): array
    {
        $subscription = $user->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            throw new \InvalidArgumentException('You do not have an active subscription to change.');
        }

        $priceId = $this->requirePrice($plan, $interval);
        $target = $this->entitlements->resolve($plan);
        $extras = $this->entitlements->extraLocations($user);
        $extraPriceId = $this->catalogue->extraLocationPriceId();

        $locations = $user->businesses()->count();
        $allowed = $target->locationLimit($target->extraLocationsAllowed ? $extras : 0);
        if ($locations > $allowed) {
            throw new \InvalidArgumentException(
                "You have {$locations} locations set up and the {$target->planName} plan covers {$allowed}. "
                .'Get in touch and we will help you choose which to keep.'
            );
        }

        $prices = [$priceId];
        if ($target->extraLocationsAllowed && $extras > 0 && $extraPriceId) {
            $prices[$extraPriceId] = ['quantity' => $extras];
        }

        $subscription->swap($prices);

        Log::info("SubscriptionService: User {$user->id} changed plan to {$plan} ({$interval})");

        return ['plan' => $plan, 'interval' => $interval, 'status' => $subscription->fresh()->stripe_status];
    }

    /**
     * Add one paid location to an Agency subscription.
     */
    public function addExtraLocation(User $user): void
    {
        $priceId = $this->catalogue->extraLocationPriceId();
        $subscription = $user->subscription('default');

        if (! $priceId || ! $subscription || ! $subscription->valid()) {
            throw new \InvalidArgumentException('Extra locations are not available on this account yet. Please get in touch.');
        }

        if ($subscription->hasPrice($priceId)) {
            $subscription->incrementQuantity(1, $priceId);
        } else {
            $subscription->addPrice($priceId, 1);
        }

        Log::info("SubscriptionService: User {$user->id} added an extra location");
    }

    /**
     * Subscribe with a payment method collected in-app. Kept for API callers;
     * the Billing page uses checkoutUrl() instead.
     */
    public function subscribe(User $user, string $planName, string $paymentMethodId, string $interval = PlanCatalogue::INTERVAL_MONTHLY): array
    {
        $priceId = $this->requirePrice($planName, $interval);

        if (! $user->stripe_id) {
            $user->createAsStripeCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'metadata' => ['user_id' => $user->id],
                'tax' => ['ip_address' => request()->ip()],
            ]);
        }

        $user->updateDefaultPaymentMethod($paymentMethodId);

        try {
            $subscription = $user->newSubscription('default', $priceId)
                ->withMetadata(['plan' => $planName, 'interval' => $interval, 'user_id' => $user->id])
                // VAT comes from User::taxRates(), as it does for Checkout.
                ->create($paymentMethodId);

            Log::info("SubscriptionService: User {$user->id} subscribed to {$planName} ({$interval})");

            $this->resumePausedPosts($user);

            return [
                'plan' => $planName,
                'interval' => $interval,
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
     * Give paused posts fresh slots now that the account has a plan again.
     *
     * While there was no plan, the dispatcher left scheduled posts alone, so
     * any whose time passed are still waiting. Sending them all at once would
     * dump days of posts into one minute, so each is moved to the next free
     * slot on its platform instead, oldest first.
     */
    public function resumePausedPosts(User $user): int
    {
        $moved = 0;

        foreach ($user->businesses()->with('settings')->get() as $business) {
            $overdue = Post::where('business_id', $business->id)
                ->where('status', Post::STATUS_SCHEDULED)
                ->where('scheduled_at', '<', now())
                ->orderBy('scheduled_at')
                ->get();

            foreach ($overdue as $post) {
                // Clear the old time first, or the post would count as a clash
                // with the very slot it is being given.
                $post->update(['scheduled_at' => null]);
                $post->update(['scheduled_at' => $this->scheduling->getNextSlot($business, $post->platform)]);
                $moved++;
            }
        }

        if ($moved > 0) {
            Log::info("SubscriptionService: Rescheduled {$moved} paused posts for user {$user->id}");
        }

        return $moved;
    }

    private function requirePrice(string $plan, string $interval): string
    {
        $key = $this->catalogue->normalise($plan);
        $definition = $key ? $this->catalogue->get($key) : null;

        if (! $definition || ! ($definition['offered'] ?? false)) {
            throw new \InvalidArgumentException('That plan is not available.');
        }

        $priceId = $this->catalogue->priceId($key, $interval);
        if (! $priceId) {
            throw new \InvalidArgumentException(
                $interval === PlanCatalogue::INTERVAL_ANNUAL
                    ? 'Annual billing is not available for that plan yet.'
                    : 'That plan is not available yet.'
            );
        }

        return $priceId;
    }
}
