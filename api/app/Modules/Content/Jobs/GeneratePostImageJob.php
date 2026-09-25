<?php

namespace App\Modules\Content\Jobs;

use App\Models\Post;
use App\Modules\Billing\Services\EntitlementService;
use App\Modules\Media\Services\ImageLibraryService;
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

        if (! config('services.openai_images.enabled')) {
            return;
        }

        // The post already has a picture (a library photo), or the owner took
        // its picture off while this was queued. Either way, paying for an AI
        // image now would override their choice.
        $this->post->refresh();
        if (! empty($this->post->media_urls) || ! empty($this->post->ai_metadata['image_removed'])) {
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

            $imageUrl = Storage::disk('s3')->url($storedPath);
            $mediaUrls = $this->post->media_urls ?? [];
            $mediaUrls[] = $imageUrl;

            // Listed in the photo library so the owner can see it and switch it
            // off. ImagePicker never reuses AI images, so this is for display only.
            $libraryImage = app(ImageLibraryService::class)->recordAiImage($business, $storedPath, $imageUrl, $imageData);

            $this->post->update([
                'media_urls' => $mediaUrls,
                'ai_metadata' => array_merge($this->post->ai_metadata ?? [], [
                    'image' => ['source' => 'ai', 'business_image_id' => $libraryImage?->id],
                ]),
            ]);

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
        return "An everyday detail from a UK {$business->industry} business: the premises, the tools of the trade or a finished piece of work.";
    }

    /**
     * Wrap the post's image idea in the house photo style.
     *
     * Two things made the first samples read as AI. People doing the work,
     * which also implies staff and jobs that do not exist, so people are out
     * entirely. And polish: perfect light, perfect symmetry, glossy surfaces,
     * everything spotless. Real small business photos are taken on a phone in
     * whatever light there is, slightly off level, with the clutter left in,
     * so the prompt asks for exactly that.
     */
    private function enhancePrompt(string $basePrompt, string $industry): string
    {
        $style = 'An ordinary, unedited photo taken on a mid-range smartphone by the business owner, in the UK. '
            .'Candid and a little imperfect: framing slightly off-centre or tilted, flat or mixed natural light, '
            .'some background clutter, visible wear, scuffs, dust and fingerprints, muted true-to-life colours, '
            .'ordinary depth of field. Not a professional shoot: no dramatic lighting, no golden glow, no heavy '
            .'background blur, no HDR, no glossy or airbrushed surfaces, nothing perfectly symmetrical or spotless. '
            .'No people, faces, hands or body parts. '
            // Generated lettering comes out garbled (a van reading "PLUMBNIG"),
            // and a made-up logo looks like someone else's business.
            .'No text, words, letters, numbers, logos, signage, screens showing text or watermarks anywhere in the image.';

        return rtrim($basePrompt, ". \n").'. '.$style;
    }
}
