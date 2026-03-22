<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Auth\AuthServiceProvider::class,
    App\Modules\Billing\BillingServiceProvider::class,
    App\Modules\Content\ContentServiceProvider::class,
    App\Modules\Social\SocialServiceProvider::class,
    App\Modules\Scraping\ScrapingServiceProvider::class,
];
