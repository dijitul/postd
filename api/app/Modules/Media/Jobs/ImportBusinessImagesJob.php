<?php

namespace App\Modules\Media\Jobs;

use App\Models\Business;
use App\Modules\Media\Services\ImageLibraryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fill a business's photo library from its website and Google profile.
 *
 * Queued by ScrapeBusinessJob once the crawl is done, rather than run inside
 * it, so the downloads get their own time budget and a slow image host or a
 * Google error can never fail or time out the scrape that the post text
 * depends on. Each source has its own 40 second budget; with the 8 second
 * per-download timeout on top that is well inside the timeout here.
 */
class ImportBusinessImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 150;

    // Imports are safe to repeat (duplicates are skipped by hash), but there
    // is no point hammering a site that just failed. Next week's run retries.
    public int $tries = 1;

    /**
     * @param  array<int, array{url: string, page_url: string|null}>  $candidates
     */
    public function __construct(
        public readonly Business $business,
        public readonly array $candidates = []
    ) {
        $this->onQueue('scraping');
    }

    public function handle(ImageLibraryService $library): void
    {
        try {
            $library->importFromUrls($this->business, $this->candidates);
        } catch (\Throwable $e) {
            Log::warning("ImportBusinessImagesJob: Website photos failed for business {$this->business->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $library->importFromGoogle($this->business);
        } catch (\Throwable $e) {
            Log::info("ImportBusinessImagesJob: Google photos failed for business {$this->business->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
