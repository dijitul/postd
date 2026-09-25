<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\EntitlementService;
use App\Modules\Billing\Services\PlanCatalogue;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly EntitlementService $entitlements,
        private readonly PlanCatalogue $catalogue
    ) {}

    /**
     * Plans, the account's current plan, and its usage against its limits.
     * Everything the Billing page shows comes from here.
     */
    public function plans(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentPlan = $user->activePlanName();
        $trialCaps = $this->catalogue->trialCaps();

        return response()->json([
            'plans' => $this->subscriptionService->getPlans($currentPlan),
            'extra_location' => $this->subscriptionService->extraLocationOffer(),
            'trial' => [
                'days' => (int) config('plans.trial_days', 14),
                'plan' => $this->catalogue->trialPlan(),
                'twitter_posts' => $trialCaps['twitter_posts'] ?? null,
                'ai_images' => $trialCaps['ai_images'] ?? null,
            ],
            'current_plan' => $currentPlan,
            'current_interval' => $user->subscribedInterval(),
            'subscribed' => $user->subscribed('default'),
            'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
            'is_on_trial' => $user->isOnValidTrial(),
            'account' => $this->entitlements->summary($user, $user->business),
        ]);
    }

    /**
     * Choose a plan. New subscribers are sent to Stripe Checkout; existing ones
     * are moved across straight away, prorated by Stripe.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys($this->catalogue->offered()))],
            'interval' => ['nullable', Rule::in([PlanCatalogue::INTERVAL_MONTHLY, PlanCatalogue::INTERVAL_ANNUAL])],
        ]);

        $user = $request->user();
        $interval = $validated['interval'] ?? PlanCatalogue::INTERVAL_MONTHLY;
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        try {
            if ($user->subscribed('default')) {
                $result = $this->subscriptionService->changePlan($user, $validated['plan'], $interval);

                return response()->json([
                    'message' => "You are now on the {$this->catalogue->name($validated['plan'])} plan.",
                    'subscription' => $result,
                ]);
            }

            $url = $this->subscriptionService->checkoutUrl(
                $user,
                $validated['plan'],
                $interval,
                $frontend.'/billing?checkout=success',
                $frontend.'/billing?checkout=cancelled'
            );

            return response()->json(['url' => $url]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => 'plan_unavailable'], 422);
        } catch (\Throwable $e) {
            Log::error('BillingController: checkout failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'We could not start the checkout just now. Please try again in a moment.',
                'error' => 'checkout_failed',
            ], 422);
        }
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys($this->catalogue->offered()))],
            'interval' => ['nullable', Rule::in([PlanCatalogue::INTERVAL_MONTHLY, PlanCatalogue::INTERVAL_ANNUAL])],
            'payment_method_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->subscriptionService->subscribe(
                $request->user(),
                $request->plan,
                $request->payment_method_id,
                $request->input('interval', PlanCatalogue::INTERVAL_MONTHLY)
            );

            return response()->json([
                'message' => 'Subscription created successfully.',
                'subscription' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Subscription failed: '.$e->getMessage(),
                'error' => 'subscription_failed',
            ], 422);
        }
    }

    /**
     * Cancel the subscription at the end of the current billing period.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->subscribed()) {
            return response()->json([
                'message' => 'You do not have an active subscription.',
                'error' => 'not_subscribed',
            ], 422);
        }

        $user->subscription()->cancel();

        return response()->json([
            'message' => 'Your subscription has been cancelled. You will continue to have access until the end of your current billing period.',
            'ends_at' => $user->subscription()->ends_at?->toIso8601String(),
        ]);
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->subscription()?->onGracePeriod()) {
            return response()->json([
                'message' => 'Subscription cannot be resumed.',
                'error' => 'cannot_resume',
            ], 422);
        }

        $user->subscription()->resume();

        return response()->json([
            'message' => 'Your subscription has been resumed.',
        ]);
    }

    /**
     * Get invoice list.
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        $invoices = $user->invoices()->map(fn ($invoice) => [
            'id' => $invoice->id,
            'date' => $invoice->date()->toDateString(),
            'total' => $invoice->total(),
            'subtotal' => $invoice->subtotal(),
            'tax' => $invoice->tax(),
            'status' => $invoice->status,
            'currency' => 'GBP',
        ]);

        return response()->json(['invoices' => $invoices]);
    }

    /**
     * Download an invoice as PDF.
     */
    public function downloadInvoice(Request $request, string $id): mixed
    {
        $user = $request->user();

        return $user->downloadInvoice($id, [
            'vendor' => 'postd.uk',
            'product' => 'Social Media Management',
        ]);
    }

    /**
     * Create a Stripe Billing Portal session.
     */
    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();

        // Create a Stripe customer if they don't have one yet
        if (! $user->stripe_id) {
            $user->createAsStripeCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'metadata' => ['user_id' => $user->id],
            ]);
        }

        // /billing is the page's actual route. /settings/billing never existed,
        // so leaving the portal dropped people on the landing page.
        $session = $user->billingPortalUrl(
            rtrim((string) config('app.frontend_url'), '/').'/billing'
        );

        return response()->json(['url' => $session]);
    }

    /**
     * Update the default payment method.
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => ['required', 'string'],
        ]);

        try {
            $request->user()->updateDefaultPaymentMethod($request->payment_method_id);

            return response()->json(['message' => 'Payment method updated.']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update payment method: '.$e->getMessage(),
                'error' => 'payment_method_update_failed',
            ], 422);
        }
    }

    /**
     * Get current subscription details.
     */
    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription();

        return response()->json([
            'subscribed' => $user->subscribed(),
            // A comped account keeps full access with no payment and no end date,
            // but nothing here said so, so the frontend saw a future trial_ends_at
            // and told the user their access was about to lapse.
            'comped' => $user->isComped(),
            'comped_plan' => $user->comped_plan,
            'comped_until' => $user->comped_until?->toIso8601String(),
            'on_trial' => $user->isOnValidTrial(),
            'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
            'subscription' => $subscription ? [
                'plan' => $user->activePlanName(),
                'interval' => $user->subscribedInterval(),
                'status' => $subscription->stripe_status,
                'current_period_end' => $subscription->ends_at?->toIso8601String(),
                'cancel_at_period_end' => $subscription->onGracePeriod(),
            ] : null,
            'payment_method' => [
                'type' => $user->pm_type,
                'last_four' => $user->pm_last_four,
            ],
        ]);
    }
}
