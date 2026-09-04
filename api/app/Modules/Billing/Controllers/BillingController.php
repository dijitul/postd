<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Get available plans.
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = $this->subscriptionService->getPlans();
        $currentPlan = $request->user()->activePlanName();

        return response()->json([
            'plans' => $plans,
            'current_plan' => $currentPlan,
            'trial_ends_at' => $request->user()->trial_ends_at?->toIso8601String(),
            'is_on_trial' => $request->user()->isOnValidTrial(),
        ]);
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => ['required', Rule::in(['starter', 'growth', 'pro'])],
            'payment_method_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->subscriptionService->subscribe(
                $request->user(),
                $request->plan,
                $request->payment_method_id
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

        $session = $user->billingPortalUrl(
            config('app.frontend_url').'/settings/billing'
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

    /**
     * Add the TikTok add-on to an existing subscription.
     */
    public function addTikTokAddon(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->subscribed()) {
            return response()->json([
                'message' => 'You must have an active subscription to add the TikTok add-on.',
                'error' => 'not_subscribed',
            ], 422);
        }

        if ($user->activePlanName() === 'pro') {
            return response()->json([
                'message' => 'TikTok is already included in your Pro plan.',
                'error' => 'already_included',
            ], 422);
        }

        try {
            $addonPriceId = config('cashier.plans.tiktok_addon.stripe_price_id');
            $user->subscription()->addPrice($addonPriceId);

            return response()->json(['message' => 'TikTok add-on added successfully.']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to add TikTok add-on: '.$e->getMessage(),
                'error' => 'addon_failed',
            ], 422);
        }
    }
}
