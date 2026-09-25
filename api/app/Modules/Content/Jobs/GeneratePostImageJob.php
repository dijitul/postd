<?php

namespace App\Modules\Content\Jobs;

use App\Models\Post;
use App\Modules\Billing\Services\EntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class GeneratePostImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 90;
    public int $tries = 2;

    public function __construct(
        public readonly Post $post,
        public readonly ?string $imagePrompt = null
    ) {
        $this->onQueue('generation');
    }

    public function handle(EntitlementService $entitlements): void
    {
        if (! $this->post->exists || $this->post->status === Post::STATUS_REJECTED) {
            return;
        }

        $business = $this->post->business;

        // Checked here as well as when queued: one generation run queues an
        // image per post before any of them has been made, so only this point
        // sees the true count. Past the monthly allowance the post keeps its
        // text and goes out without an image; nothing is held back.
        if ($entitlements->aiImagesRemaining($business) < 1) {
            Log::info("GeneratePostImageJob: Skipping post {$this->post->id} - AI image allowance used for this period", [
                'business_id' => $business->id,
            ]);
            return;
        }
        $prompt = $this->imagePrompt ?? $this->buildDefaultPrompt($business);

        // Enhance the prompt with UK-specific and brand-consistent instructions
        $enhancedPrompt = $this->enhancePrompt($prompt, $business->industry);

        $model = config('services.openai_images.model');

        try {
            // GPT Image models always return the image itself as base64. The
            // old DALL-E request asked for a URL (response_format), which the
            // API now rejects outright.
            $response = OpenAI::images()->create([
                'model' => $model,
                'prompt' => $enhancedPrompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => config('services.openai_images.quality'),
                'output_format' => 'jpeg',
            ]);

            $imageData = base64_decode($response->data[0]->b64_json, true);

            if (! $imageData) {
                throw new \RuntimeException('The image API returned no image data.');
            }

            $storedPath = "posts/{$business->id}/{$this->post->id}/".uniqid('img_', true).'.jpg';
            Storage::disk('s3')->put($storedPath, $imageData, 'public');

            $mediaUrls = $this->post->media_urls ?? [];
            $mediaUrls[] = Storage::disk('s3')->url($storedPath);

            $this->post->update(['media_urls' => $mediaUrls]);

            Log::info("GeneratePostImageJob: Image generated for post {$this->post->id}", [
                'path' => $storedPath,
                'model' => $model,
            ]);

            \App\Models\AiCostLog::create([
                'business_id' => $business->id,
                'post_id' => $this->post->id,
                'operation' => 'image_generation',
                'model' => $model,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cost_usd' => config('services.openai_images.cost_usd'),
            ]);
        } catch (\Throwable $e) {
            // The post still goes out as text, so a failure here is easy to
            // miss: images silently stopped for five months once already.
            // Recording it on the post makes it visible in the admin and in
            // any query for posts that should have had an image.
            $this->post->update(['ai_metadata' => array_merge($this->post->ai_metadata ?? [], [
                'image_error' => mb_substr($e->getMessage(), 0, 300),
                'image_error_at' => now()->toIso8601String(),
            ])]);

            Log::error("GeneratePostImageJob: Failed for post {$this->post->id}", [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildDefaultPrompt(mixed $business): string
    {
        return "A professional, authentic photograph representing a UK {$business->industry} business called {$business->name}. "
            ."Natural lighting, real setting, UK aesthetic. Not a generic stock photo.";
    }

    private function enhancePrompt(string $basePrompt, string $industry): string
    {
        $ukStyle = "UK setting, authentic British aesthetic, natural photography style, not stock-photo-generic. "
            ."Warm, inviting atmosphere. Professional but approachable. "
            // Generated lettering comes out garbled (a van reading "PLUMBNIG"),
            // and a made-up logo looks like someone else's business.
            ."No text, words, letters, logos, signage or watermarks anywhere in the image.";

        return "{$basePrompt}. {$ukStyle}";
    }
}
