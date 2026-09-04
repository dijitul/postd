<?php

namespace App\Modules\Content\Jobs;

use App\Models\Business;
use App\Models\ContentBrief;
use App\Models\ContentSource;
use App\Modules\Content\Services\ContentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePostsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // One run now tops a whole week of posts back up across every connected
    // platform, so this is many Anthropic calls rather than one per platform.
    // Keep it below the queue connection's retry_after or the job gets picked up
    // a second time mid-run.
    public int $timeout = 900;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public readonly Business $business,
        public readonly ?ContentBrief $brief = null,
        public readonly string $sourceType = 'scheduled'
    ) {
        $this->onQueue('generation');
    }

    public function handle(ContentGenerationService $generationService): void
    {
        if (! $this->business->onboarding_complete) {
            Log::info("GeneratePostsJob: Skipping - business {$this->business->id} not onboarded");
            return;
        }

        if ($this->business->socialConnections()->where('is_active', true)->doesntExist()) {
            Log::info("GeneratePostsJob: Skipping - no active social connections for business {$this->business->id}");
            return;
        }

        $brief = $this->brief ?? $this->createScheduledBrief();

        if (! $brief) {
            Log::warning("GeneratePostsJob: Could not create or find brief for business {$this->business->id}");
            return;
        }

        Log::info("GeneratePostsJob: Generating posts for business {$this->business->id}, brief {$brief->id}");

        $posts = $generationService->generateFromBrief($brief);

        Log::info("GeneratePostsJob: Generated ".count($posts)." posts for business {$this->business->id}");

        $this->business->update(['last_generated_at' => now()]);
    }

    /**
     * Create a "scheduled weekly" brief when no specific brief is provided.
     */
    private function createScheduledBrief(): ContentBrief
    {
        // Look for unused content sources first
        $source = $this->business->contentSources()
            ->where('processed', false)
            ->whereIn('type', [ContentSource::TYPE_REVIEW, ContentSource::TYPE_NEWS])
            ->latest('scraped_at')
            ->first();

        $theme = 'Weekly business update';
        $keyMessages = "Showcase {$this->business->name}'s expertise, services, and value to customers.";
        $referenceData = [];

        if ($source) {
            if ($source->type === ContentSource::TYPE_REVIEW) {
                $theme = 'Customer trust and social proof';
                $keyMessages = "Show what it is actually like to work with {$this->business->name}.";

                // Deliberately no review text here. It used to be copied in with
                // no author attached, and the model, handed an unattributed quote,
                // guessed who said it: a customer's words about Olly went out
                // credited to Olly. Reviews now reach the model through the angle
                // rotation instead, with the reviewer's name beside the quote.
                $referenceData = [];
            } elseif ($source->type === ContentSource::TYPE_NEWS) {
                $theme = 'Local news hook';
                $keyMessages = "Connect the business to a relevant local or industry news story.";
                $referenceData = $source->structured_data ?? [];
            }
        }

        return ContentBrief::create([
            'business_id' => $this->business->id,
            'source_id' => $source?->id,
            'theme' => $theme,
            'key_messages' => $keyMessages,
            'source_type' => $source?->type ?? 'scheduled',
            'reference_data' => $referenceData,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GeneratePostsJob: Failed for business {$this->business->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
