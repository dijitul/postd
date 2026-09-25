<?php

namespace App\Modules\Scraping\Jobs;

use App\Models\Business;
use App\Models\ContentSource;
use App\Modules\Scraping\Services\GoogleReviewsService;
use App\Modules\Scraping\Services\WebsiteScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeBusinessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // The website crawl stops starting new pages after 60 seconds, and a page
    // fetch can take up to 8 more, with the reviews fetch still to follow.
    public int $timeout = 180;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Business $business
    ) {
        $this->onQueue('scraping');
    }

    public function handle(
        WebsiteScraperService $scraperService,
        GoogleReviewsService $reviewsService
    ): void {
        Log::info("ScrapeBusinessJob: Starting for business {$this->business->id}");

        $scrapeCount = 0;

        // Scrape the website if a URL is provided
        if ($this->business->website_url) {
            $this->scrapeWebsite($scraperService);
            $scrapeCount++;
        }

        // Fetch Google Reviews if a URL is provided
        if ($this->business->google_reviews_url) {
            $reviewSources = $reviewsService->fetchReviews($this->business);
            $scrapeCount += count($reviewSources);

            Log::info("ScrapeBusinessJob: Fetched ".count($reviewSources)." review sources for business {$this->business->id}");
        }

        // Update the last scraped timestamp
        $this->business->update(['last_scraped_at' => now()]);

        Log::info("ScrapeBusinessJob: Completed for business {$this->business->id}. Created {$scrapeCount} content sources.");
    }

    private function scrapeWebsite(WebsiteScraperService $scraperService): void
    {
        try {
            $data = $scraperService->scrape($this->business->website_url);

            // Update the business with any useful structured data
            $updates = [];
            if (! $this->business->description && $data['meta_description']) {
                $updates['description'] = $data['meta_description'];
            }
            if (! empty($data['services'])) {
                $updates['usp_notes'] = implode(', ', array_slice($data['services'], 0, 10));
            }
            if (! empty($updates)) {
                $this->business->update($updates);
            }

            // When each page was first seen, carried across scrapes, so content
            // generation can tell a page the business has just published from
            // one that has sat there for years. On the very first scrape
            // everything is new to us but nothing is new to the business, so
            // those pages are backdated and never announced as fresh.
            $existing = ContentSource::where('business_id', $this->business->id)
                ->where('type', ContentSource::TYPE_WEBSITE)
                ->where('source_url', $this->business->website_url)
                ->first();

            // A row scraped before first-seen dates were kept counts as a first
            // scrape too, or every page of every existing site would be announced
            // as new the day this shipped.
            $firstSeen = $existing?->structured_data['page_first_seen'] ?? null;
            $isFirstScrape = $firstSeen === null;
            $firstSeen ??= [];

            foreach ($data['page_text'] as $page) {
                $firstSeen[$page['url']] ??= $isFirstScrape ? '1970-01-01' : now()->toDateString();
            }

            // Store as a content source
            ContentSource::updateOrCreate(
                [
                    'business_id' => $this->business->id,
                    'type' => ContentSource::TYPE_WEBSITE,
                    'source_url' => $this->business->website_url,
                ],
                [
                    'raw_data' => $data['body_text'],
                    'structured_data' => [
                        'page_title' => $data['page_title'],
                        'meta_description' => $data['meta_description'],
                        'h1_headings' => $data['h1_headings'],
                        'h2_headings' => $data['h2_headings'],
                        'services' => $data['services'],
                        'key_phrases' => $data['key_phrases'],
                        'opening_hours' => $data['opening_hours'],
                        'phone_numbers' => $data['phone_numbers'],
                        'email_addresses' => $data['email_addresses'],
                        'location_mentions' => $data['location_mentions'],
                        // Per-page copy, so content generation can quote a line and
                        // say which page of the site it came from.
                        'page_text' => $data['page_text'],
                        'page_first_seen' => $firstSeen,
                    ],
                    'scraped_at' => now(),
                    'processed' => false,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("ScrapeBusinessJob: Website scrape failed for business {$this->business->id}", [
                'url' => $this->business->website_url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ScrapeBusinessJob: All retries exhausted for business {$this->business->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
