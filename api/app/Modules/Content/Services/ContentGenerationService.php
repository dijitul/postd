<?php

namespace App\Modules\Content\Services;

use App\Models\Business;
use App\Models\ContentBrief;
use App\Models\ContentSource;
use App\Models\Post;
use App\Modules\Schedule\Services\SchedulingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentGenerationService
{
    // Claude model to use for content generation
    private const MODEL = 'claude-haiku-4-5-20251001';

    // Model costs (USD per 1M tokens) for cost tracking
    private const MODEL_COSTS = [
        'claude-sonnet-4-6'          => ['input' => 3.00,  'output' => 15.00],
        'claude-haiku-4-5-20251001'  => ['input' => 0.80,  'output' => 4.00],
        'dall-e-3'                   => ['per_image' => 0.04],
    ];

    // Platform-specific generation rules from the spec
    private const PLATFORM_RULES = [
        'facebook' => [
            'format' => 'Story with CTA',
            'tone' => 'Warm, conversational',
            'min_words' => 80,
            'max_words' => 200,
            'emojis' => true,
            'hashtags' => 'minimal (0-2)',
            'special' => 'End with a clear call-to-action. Make it feel like a friend sharing something useful.',
        ],
        'instagram' => [
            'format' => 'Visual-first caption',
            'tone' => 'Aspirational, lifestyle-focused',
            'min_words' => 50,
            'max_words' => 100,
            'emojis' => true,
            'hashtags' => '5-10 relevant hashtags at the end',
            'special' => 'Start with a strong hook line. Describe what the accompanying image would look like.',
        ],
        'twitter' => [
            'format' => 'Punchy, opinionated tweet',
            'tone' => 'Direct, witty, confident',
            'max_chars' => 260,
            'emojis' => 'sparingly',
            'hashtags' => '1-2 hashtags maximum',
            'special' => 'Either a bold statement or a question that sparks engagement. No fluff.',
        ],
        'linkedin' => [
            'format' => 'Professional insight post',
            'tone' => 'Expert, thoughtful, value-led',
            'min_words' => 120,
            'max_words' => 300,
            'emojis' => false,
            'hashtags' => '2-3 professional hashtags at the end',
            'special' => 'Lead with an insight or observation. Avoid corporate speak. Be genuinely useful.',
        ],
        'tiktok' => [
            'format' => 'Video script',
            'tone' => 'Energetic, authentic, relatable',
            'duration_seconds' => '30-60',
            'emojis' => true,
            'special' => 'Hook in first 3 seconds is critical. Structure: Hook → Problem/Story → Value → CTA. Write as a natural spoken script.',
        ],
        'google_business_profile' => [
            'format' => 'Business update post',
            'tone' => 'Clear, helpful, professional',
            'min_words' => 60,
            'max_words' => 150,
            'emojis' => false,
            'hashtags' => 'none',
            'special' => 'Include a specific CTA. Mention location/area served when relevant. Focus on factual updates.',
        ],
    ];

    public function __construct(
        private readonly SchedulingService $schedulingService
    ) {}

    /**
     * Generate a full cascade of posts from a ContentBrief.
     * One brief → one platform-native post per connected platform.
     */
    public function generateFromBrief(ContentBrief $brief): array
    {
        $business = $brief->business()->with(['settings', 'activeSocialConnections'])->first();
        $platforms = $business->connectedPlatforms();

        if (empty($platforms)) {
            Log::warning("ContentGenerationService: No connected platforms for business {$business->id}");
            return [];
        }

        $businessContext = $this->buildBusinessContext($business, $brief);
        $generatedPosts = [];

        foreach ($platforms as $platform) {
            try {
                $post = $this->generateForPlatform($business, $brief, $platform, $businessContext);
                if ($post) {
                    $generatedPosts[] = $post;
                }
            } catch (\Throwable $e) {
                Log::error("ContentGenerationService: Failed to generate for {$platform}", [
                    'business_id' => $business->id,
                    'brief_id' => $brief->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark the brief as having generated posts
        $brief->update([
            'posts_generated' => true,
            'posts_generated_at' => now(),
        ]);

        // Mark the source as processed
        if ($brief->source_id) {
            $brief->source()->update(['processed' => true]);
        }

        return $generatedPosts;
    }

    /**
     * Generate a post for a single platform using Claude.
     */
    private function generateForPlatform(
        Business $business,
        ContentBrief $brief,
        string $platform,
        array $businessContext
    ): ?Post {
        $platformRules = self::PLATFORM_RULES[$platform] ?? null;
        if (! $platformRules) {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt($business, $platform, $platformRules);
        $userPrompt   = $this->buildUserPrompt($brief, $businessContext, $platform);

        $startTime = microtime(true);

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => config('services.anthropic.version'),
            'content-type'      => 'application/json',
        ])->post(config('services.anthropic.base_url').'/messages', [
            'model'      => self::MODEL,
            'max_tokens' => $platform === 'tiktok' ? 600 : 400,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        $durationMs = (microtime(true) - $startTime) * 1000;

        if ($response->failed()) {
            Log::error("ContentGenerationService: Anthropic API error for {$platform}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $body       = $response->json();
        $rawContent = $body['content'][0]['text'] ?? '';
        $usage      = $body['usage'] ?? [];

        $inputTokens  = $usage['input_tokens']  ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $parsed = $this->extractJson($rawContent);

        if (! $parsed || ! isset($parsed['content'])) {
            Log::error("ContentGenerationService: Bad response format for {$platform}", [
                'raw' => $rawContent,
            ]);
            return null;
        }

        // Calculate token costs
        $costUsd = $this->calculateCost(self::MODEL, $inputTokens, $outputTokens);

        // Log AI cost
        $this->logAiCost($business, null, 'text_generation', self::MODEL, $inputTokens, $outputTokens, $costUsd);

        // Determine the connection for this platform
        $connection = $business->getConnectionForPlatform($platform);

        // Get the optimal scheduled time
        $scheduledAt = $this->schedulingService->getNextSlot($business, $platform);

        // Build the Post record
        $settings = $business->settings;
        $requiresApproval = $settings ? ! $settings->auto_approve_posts : true;

        $post = Post::create([
            'business_id'       => $business->id,
            'brief_id'          => $brief->id,
            'connection_id'     => $connection?->id,
            'platform'          => $platform,
            'content'           => $parsed['content'],
            'hashtags'          => $parsed['hashtags'] ?? [],
            'status'            => $requiresApproval ? Post::STATUS_PENDING : Post::STATUS_APPROVED,
            'scheduled_at'      => $scheduledAt,
            'requires_approval' => $requiresApproval,
            'ai_metadata'       => [
                'model'             => self::MODEL,
                'prompt_tokens'     => $inputTokens,
                'completion_tokens' => $outputTokens,
                'cost_usd'          => $costUsd,
                'duration_ms'       => round($durationMs, 2),
                'brief_id'          => $brief->id,
                'source_type'       => $brief->source_type,
            ],
        ]);

        // If auto-approved, schedule it immediately
        if (! $requiresApproval && $scheduledAt) {
            $post->schedule($scheduledAt);
        }

        // Queue image generation if the platform benefits from it and images are enabled
        if ($this->platformNeedsImage($platform) && ($business->settings?->generate_images ?? true)) {
            \App\Modules\Content\Jobs\GeneratePostImageJob::dispatch($post, $parsed['image_prompt'] ?? null)
                ->onQueue('generation')
                ->delay(now()->addSeconds(5));
        }

        return $post;
    }

    /**
     * Build the system prompt with platform rules and business voice.
     */
    private function buildSystemPrompt(Business $business, string $platform, array $rules): string
    {
        $toneDescription = match ($business->tone) {
            'professional' => 'polished and authoritative — like a trusted expert speaking',
            'friendly'     => 'warm and approachable — like a knowledgeable friend recommending something',
            'casual'       => 'relaxed and conversational — like chatting over a coffee',
            default        => 'friendly and approachable',
        };

        $platformName = $this->getPlatformDisplayName($platform);

        return <<<PROMPT
You are a social media content expert writing for {$business->name}, a UK business in the {$business->industry} industry.

BRAND VOICE: {$toneDescription}

PLATFORM: {$platformName}
FORMAT: {$rules['format']}
TONE: {$rules['tone']}
SPECIAL RULES: {$rules['special']}

CRITICAL RULES:
- Write in UK English ONLY (use "organise" not "organize", "colour" not "color", "behaviour" not "behavior", etc.)
- Never use American spelling or idioms
- Sound genuinely human and authentic — never corporate or robotic
- Do NOT use em dashes (—) or excessive ellipsis (...)
- {$this->getHashtagRule($rules)}
- {$this->getLengthRule($rules)}

Respond with ONLY a valid JSON object — no markdown, no code fences, no commentary before or after. Use this exact structure:
{
  "content": "the full post text ready to publish",
  "hashtags": ["hashtag1", "hashtag2"],
  "image_prompt": "a detailed prompt for an accompanying image (UK-appropriate, authentic photography style, not generic stock photo)"
}
PROMPT;
    }

    /**
     * Build the user prompt with business context and brief details.
     */
    private function buildUserPrompt(ContentBrief $brief, array $context, string $platform): string
    {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
Create a {$platform} post based on the following brief and business context.

BRIEF:
Theme: {$brief->theme}
Key messages to communicate: {$brief->key_messages}
Tone notes: {$brief->tone_notes}
Source type: {$brief->source_type}

BUSINESS CONTEXT:
{$contextStr}

{$this->getReferenceDataPrompt($brief)}

Generate a post that feels native to {$platform} — not like it was copied from another platform.
The post should naturally reflect the brief theme whilst sounding completely authentic.
PROMPT;
    }

    /**
     * Build the full business context array for the AI prompt.
     */
    private function buildBusinessContext(Business $business, ContentBrief $brief): array
    {
        $context = [
            'business_name'    => $business->name,
            'industry'         => $business->industry,
            'location'         => implode(', ', array_filter([$business->city, $business->postcode, 'UK'])),
            'description'      => $business->description,
            'usp_notes'        => $business->usp_notes,
            'tone_preference'  => $business->tone,
        ];

        // Pull recent website data if available
        $websiteSource = $business->contentSources()
            ->where('type', ContentSource::TYPE_WEBSITE)
            ->latest('scraped_at')
            ->first();

        if ($websiteSource && $websiteSource->structured_data) {
            $data = $websiteSource->structured_data;
            $context['website_data'] = [
                'page_title'  => $data['page_title'] ?? null,
                'description' => $data['meta_description'] ?? null,
                'services'    => array_slice($data['services'] ?? [], 0, 5),
                'key_phrases' => array_slice($data['key_phrases'] ?? [], 0, 10),
            ];
        }

        // Pull recent review sentiment
        $recentReviews = $business->contentSources()
            ->where('type', ContentSource::TYPE_REVIEW)
            ->latest('scraped_at')
            ->limit(3)
            ->get();

        if ($recentReviews->isNotEmpty()) {
            $context['recent_reviews'] = $recentReviews->map(fn ($r) => [
                'excerpt'   => substr($r->raw_data, 0, 200),
                'sentiment' => $r->sentiment_score,
            ])->toArray();
        }

        return $context;
    }

    /**
     * Extract a JSON object from Claude's response text.
     * Handles cases where Claude wraps the JSON in markdown code fences.
     */
    private function extractJson(string $text): ?array
    {
        $text = trim($text);

        // Strip markdown code fences if present (```json ... ``` or ``` ... ```)
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $text, $m)) {
            $text = trim($m[1]);
        }

        // Try to find the first complete JSON object
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Calculate cost of an Anthropic API call.
     */
    private function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $costs = self::MODEL_COSTS[$model] ?? ['input' => 0, 'output' => 0];
        return (($promptTokens / 1_000_000) * $costs['input'])
            + (($completionTokens / 1_000_000) * $costs['output']);
    }

    /**
     * Log AI cost to the database.
     */
    private function logAiCost(
        Business $business,
        ?Post $post,
        string $operation,
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $costUsd
    ): void {
        \App\Models\AiCostLog::create([
            'business_id'       => $business->id,
            'post_id'           => $post?->id,
            'operation'         => $operation,
            'model'             => $model,
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens'      => $promptTokens + $completionTokens,
            'cost_usd'          => $costUsd,
        ]);
    }

    private function platformNeedsImage(string $platform): bool
    {
        return in_array($platform, ['facebook', 'instagram', 'linkedin', 'google_business_profile']);
    }

    private function getPlatformDisplayName(string $platform): string
    {
        return match ($platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter'                 => 'X (Twitter)',
            default                   => ucfirst($platform),
        };
    }

    private function getHashtagRule(array $rules): string
    {
        $hashtags = $rules['hashtags'] ?? 'none';
        return "Hashtags: {$hashtags}";
    }

    private function getLengthRule(array $rules): string
    {
        if (isset($rules['max_chars'])) {
            return "Maximum {$rules['max_chars']} characters total including hashtags";
        }
        $min = $rules['min_words'] ?? 0;
        $max = $rules['max_words'] ?? 300;
        return "Length: {$min}-{$max} words";
    }

    private function getReferenceDataPrompt(ContentBrief $brief): string
    {
        if (empty($brief->reference_data)) {
            return '';
        }

        $data = json_encode($brief->reference_data, JSON_PRETTY_PRINT);
        return "REFERENCE DATA (use this directly where appropriate):\n{$data}";
    }
}
