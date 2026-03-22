<?php

use App\Modules\Admin\Controllers\AdminController;
use App\Modules\Analytics\Controllers\AnalyticsController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Billing\Controllers\BillingController;
use App\Modules\Billing\Webhooks\StripeWebhookController;
use App\Modules\Content\Controllers\ContentController;
use App\Modules\Onboarding\Controllers\OnboardingController;
use App\Modules\Social\Controllers\SocialConnectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — postd.uk
|--------------------------------------------------------------------------
|
| Version: v1 (unversioned at launch, prefix added when breaking change needed)
|
*/

// Health check
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// ── Stripe Webhooks (no auth — verified by signature) ──────────────────────
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
});

// ── Auth ──────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register')
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])->name('auth.login')
        ->middleware('throttle:10,1');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password')
        ->middleware('throttle:5,1');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password')
        ->middleware('throttle:5,1');

    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1')
        ->name('verification.send');

    // Social OAuth flows
    Route::prefix('social')->group(function () {
        Route::get('/{platform}/redirect', [SocialConnectionController::class, 'redirect'])
            ->name('social.redirect');
        Route::get('/{platform}/callback', [SocialConnectionController::class, 'callback'])
            ->name('social.callback');
    });

    // Authenticated auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('auth.password.update');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
        Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('auth.account.delete');
    });
});

// ── All routes below require authentication ────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Onboarding ────────────────────────────────────────────────────────
    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status'])->name('onboarding.status');
        Route::post('/business', [OnboardingController::class, 'createBusiness'])->name('onboarding.business.create');
        Route::put('/business', [OnboardingController::class, 'updateBusiness'])->name('onboarding.business.update');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
        Route::get('/industries', [OnboardingController::class, 'industries'])->name('onboarding.industries');
    });

    // ── Social Connections ────────────────────────────────────────────────
    Route::prefix('social')->group(function () {
        Route::get('/connections', [SocialConnectionController::class, 'index'])->name('social.connections.index');
        Route::post('/connect/{platform}', [SocialConnectionController::class, 'connect'])->name('social.connect');
        Route::delete('/connections/{id}', [SocialConnectionController::class, 'disconnect'])->name('social.disconnect');
        Route::post('/connections/{id}/refresh', [SocialConnectionController::class, 'refreshToken'])->name('social.token.refresh');
        Route::get('/connections/{id}/accounts', [SocialConnectionController::class, 'accounts'])->name('social.accounts');
        Route::put('/connections/{id}/account', [SocialConnectionController::class, 'selectAccount'])->name('social.account.select');
    });

    // ── Posts / Inbox ─────────────────────────────────────────────────────
    Route::prefix('posts')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('posts.index');
        Route::get('/inbox', [ContentController::class, 'inbox'])->name('posts.inbox');
        Route::get('/{id}', [ContentController::class, 'show'])->name('posts.show');
        Route::post('/{id}/approve', [ContentController::class, 'approve'])->name('posts.approve');
        Route::post('/{id}/reject', [ContentController::class, 'reject'])->name('posts.reject');
        Route::post('/{id}/retry', [ContentController::class, 'retry'])->name('posts.retry');
        Route::put('/{id}', [ContentController::class, 'update'])->name('posts.update');
        Route::post('/idea', [ContentController::class, 'submitIdea'])->name('posts.idea');
        Route::post('/generate', [ContentController::class, 'triggerGeneration'])->name('posts.generate');
    });

    // ── Billing ───────────────────────────────────────────────────────────
    Route::prefix('billing')->group(function () {
        Route::get('/plans', [BillingController::class, 'plans'])->name('billing.plans');
        Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
        Route::post('/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('/resume', [BillingController::class, 'resume'])->name('billing.resume');
        Route::get('/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('/invoices/{id}/download', [BillingController::class, 'downloadInvoice'])->name('billing.invoice.download');
        Route::post('/portal', [BillingController::class, 'portal'])->name('billing.portal');
        Route::post('/payment-method', [BillingController::class, 'updatePaymentMethod'])->name('billing.payment-method');
        Route::get('/subscription', [BillingController::class, 'subscription'])->name('billing.subscription');
        Route::post('/tiktok-addon', [BillingController::class, 'addTikTokAddon'])->name('billing.tiktok-addon');
    });

    // ── Analytics ─────────────────────────────────────────────────────────
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
        Route::get('/platforms', [AnalyticsController::class, 'platforms'])->name('analytics.platforms');
        Route::get('/posts', [AnalyticsController::class, 'posts'])->name('analytics.posts');
    });

    // ── Settings ──────────────────────────────────────────────────────────
    Route::prefix('settings')->group(function () {
        Route::get('/', [\App\Modules\Onboarding\Controllers\OnboardingController::class, 'settings'])->name('settings.index');
        Route::put('/', [\App\Modules\Onboarding\Controllers\OnboardingController::class, 'updateSettings'])->name('settings.update');
    });

    // ── Admin (Dijitul team only) ──────────────────────────────────────────
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats'])->name('admin.stats');
        Route::get('/businesses', [AdminController::class, 'businesses'])->name('admin.businesses');
        Route::get('/businesses/{id}', [AdminController::class, 'business'])->name('admin.business');
        Route::get('/health', [AdminController::class, 'health'])->name('admin.health');
        Route::get('/at-risk', [AdminController::class, 'atRisk'])->name('admin.at-risk');
        Route::get('/ai-costs', [AdminController::class, 'aiCosts'])->name('admin.ai-costs');
        Route::post('/businesses/{id}/impersonate', [AdminController::class, 'impersonate'])->name('admin.impersonate');
    });

});
