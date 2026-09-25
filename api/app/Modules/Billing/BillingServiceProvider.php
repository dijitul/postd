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

        $this->app->singleton(
            \App\Modules\Billing\Services\PlanCatalogue::class,
            fn () => \App\Modules\Billing\Services\PlanCatalogue::fromConfig()
        );

        $this->app->singleton(
            \App\Modules\Billing\Services\EntitlementService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
