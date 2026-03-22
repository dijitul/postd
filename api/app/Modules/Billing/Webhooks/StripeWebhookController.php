<?php

namespace App\Modules\Billing\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    /**
     * Handle a Stripe webhook.
     * Extends Cashier's built-in webhook controller for custom event handling.
     */
    public function handle(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return parent::handle($request);
    }

    /**
     * Handle subscription created.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): void
    {
        parent::handleCustomerSubscriptionCreated($payload);

        $this->logWebhook('customer.subscription.created', $payload);

        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        $user = $this->getUserByStripeId($stripeCustomerId);

        if ($user) {
            Log::info("StripeWebhookController: Subscription created for user {$user->id}");
            // Any post-subscription logic here (e.g. onboarding nudges)
        }
    }

    /**
     * Handle subscription updated (e.g. plan change, renewal).
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $this->logWebhook('customer.subscription.updated', $payload);

        Log::info('StripeWebhookController: Subscription updated', [
            'subscription_id' => $payload['data']['object']['id'] ?? null,
            'status' => $payload['data']['object']['status'] ?? null,
        ]);
    }

    /**
     * Handle subscription cancelled.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $this->logWebhook('customer.subscription.deleted', $payload);

        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        $user = $this->getUserByStripeId($stripeCustomerId);

        if ($user) {
            Log::info("StripeWebhookController: Subscription cancelled for user {$user->id}");
            // Notify the user their subscription has ended
            try {
                $user->notify(new \App\Modules\Notifications\SubscriptionCancelledNotification($user));
            } catch (\Throwable $e) {
                Log::error('StripeWebhookController: Failed to send cancellation notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle successful payment.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): void
    {
        $this->logWebhook('invoice.payment_succeeded', $payload);

        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        $user = $this->getUserByStripeId($stripeCustomerId);

        if ($user) {
            Log::info("StripeWebhookController: Payment succeeded for user {$user->id}", [
                'amount' => $payload['data']['object']['amount_paid'] ?? null,
            ]);
        }
    }

    /**
     * Handle failed payment.
     */
    protected function handleInvoicePaymentFailed(array $payload): void
    {
        $this->logWebhook('invoice.payment_failed', $payload);

        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        $user = $this->getUserByStripeId($stripeCustomerId);

        if ($user) {
            Log::warning("StripeWebhookController: Payment failed for user {$user->id}");
            // Notify user of failed payment
            try {
                $user->notify(new \App\Modules\Notifications\PaymentFailedNotification($user));
            } catch (\Throwable $e) {
                Log::error('StripeWebhookController: Failed to send payment failed notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getUserByStripeId(?string $stripeId): ?\App\Models\User
    {
        if (! $stripeId) {
            return null;
        }
        return \App\Models\User::where('stripe_id', $stripeId)->first();
    }

    private function logWebhook(string $eventName, array $payload): void
    {
        try {
            \DB::table('webhook_calls')->insert([
                'name' => $eventName,
                'payload' => json_encode($payload),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('StripeWebhookController: Failed to log webhook', ['error' => $e->getMessage()]);
        }
    }
}
