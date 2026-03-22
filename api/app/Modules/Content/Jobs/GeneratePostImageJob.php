<?php

namespace App\Modules\Content\Jobs;

use App\Models\Post;
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

    public function handle(): void
    {
        if (! $this->post->exists || $this->post->status === Post::STATUS_REJECTED) {
            return;
        }

        $business = $this->post->business;
        $prompt = $this->imagePrompt ?? $this->buildDefaultPrompt($business);

        // Enhance the prompt with UK-specific and brand-consistent instructions
        $enhancedPrompt = $this->enhancePrompt($prompt, $business->industry);

        try {
            $response = OpenAI::images()->create([
                'model' => 'dall-e-3',
                'prompt' => $enhancedPrompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
                'response_format' => 'url',
            ]);

            $imageUrl = $response->data[0]->url;

            // Download and store on DigitalOcean Spaces
            $storedPath = $this->downloadAndStore($imageUrl, $business->id, $this->post->id);

            if ($storedPath) {
                $mediaUrls = $this->post->media_urls ?? [];
                $mediaUrls[] = Storage::disk('s3')->url($storedPath);

                $this->post->update(['media_urls' => $mediaUrls]);

                Log::info("GeneratePostImageJob: Image generated for post {$this->post->id}", [
                    'path' => $storedPath,
                ]);
            }

            // Log cost (DALL-E 3 standard 1024x1024 = $0.040 per image)
            \App\Models\AiCostLog::create([
                'business_id' => $business->id,
                'post_id' => $this->post->id,
                'operation' => 'image_generation',
                'model' => 'dall-e-3',
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cost_usd' => 0.04,
            ]);

        } catch (\Throwable $e) {
            Log::error("GeneratePostImageJob: Failed for post {$this->post->id}", [
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
            ."Warm, inviting atmosphere. Professional but approachable.";

        return "{$basePrompt}. {$ukStyle}";
    }

    private function downloadAndStore(string $url, string $businessId, string $postId): ?string
    {
        try {
            $imageData = file_get_contents($url);
            if ($imageData === false) {
                return null;
            }

            $filename = "posts/{$businessId}/{$postId}/".uniqid('img_', true).'.png';
            Storage::disk('s3')->put($filename, $imageData, 'public');

            return $filename;
        } catch (\Throwable $e) {
            Log::error("GeneratePostImageJob: Failed to store image", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
