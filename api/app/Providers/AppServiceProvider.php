<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind platform implementations
        $this->app->singleton(\App\Modules\Social\Platforms\FacebookPlatform::class);
        $this->app->singleton(\App\Modules\Social\Platforms\InstagramPlatform::class);
        $this->app->singleton(\App\Modules\Social\Platforms\TwitterPlatform::class);
        $this->app->singleton(\App\Modules\Social\Platforms\LinkedInPlatform::class);
        $this->app->singleton(\App\Modules\Social\Platforms\TikTokPlatform::class);
        $this->app->singleton(\App\Modules\Social\Platforms\GoogleBusinessProfilePlatform::class);
        $this->app->singleton(\App\Modules\Social\Services\SocialConnectionService::class);
        $this->app->singleton(\App\Modules\Content\Services\ContentGenerationService::class);
        $this->app->singleton(\App\Modules\Content\Services\PostDispatchService::class);
        $this->app->singleton(\App\Modules\Scraping\Services\WebsiteScraperService::class);
        $this->app->singleton(\App\Modules\Scraping\Services\GoogleReviewsService::class);
        $this->app->singleton(\App\Modules\Schedule\Services\SchedulingService::class);
    }

    public function boot(): void
    {
        // Strict models in development
        Model::shouldBeStrict(! app()->isProduction());

        // Prevent N+1 queries in development
        if (app()->isLocal()) {
            Model::preventLazyLoading();
        }

        // Remove unnecessary wrapping from JSON resources
        JsonResource::withoutWrapping();

        // Log slow queries (>500ms) in production
        if (app()->isProduction()) {
            DB::listen(function ($query) {
                if ($query->time > 500) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }

        // Rate limiters
        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('auth', fn (Request $request) =>
            Limit::perMinute(5)->by($request->ip())
        );

        RateLimiter::for('generation', fn (Request $request) =>
            Limit::perHour(10)->by($request->user()?->id ?: $request->ip())
        );
    }
}
