<?php

namespace App\Modules\Billing;

use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Billing\Services\SubscriptionService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
