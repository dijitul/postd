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
            if ($this->hasReachedWeeklyTarget($business, $platform)) {
                Log::info("ContentGenerationService: Skipping {$platform} — weekly posting target already met", [
                    'business_id' => $business->id,
                ]);
                continue;
            }

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
     * Has this platform already hit the business's configured posts-per-week target?
     *
     * Generation runs daily and previously produced one post per connected platform
     * every run, so the posts_per_week_* settings had no effect at all. Counting what
     * already exists this week makes the configured cadence the actual cadence, and
     * lets a target of 0 switch a platform off without disconnecting it.
     */
    private function hasReachedWeeklyTarget(Business $business, string $platform): bool
    {
        $settings = $business->settings;

        if (! $settings) {
            return false;
        }

        $target = $settings->getPostsPerWeekForPlatform($platform);

        if ($target <= 0) {
            return true;
        }

        $existing = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', [
                Post::STATUS_PENDING,
                Post::STATUS_APPROVED,
                Post::STATUS_SCHEDULED,
                Post::STATUS_DISPATCHING,
                Post::STATUS_POSTED,
            ])
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return $existing >= $target;
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

        // Work out when this will actually go out before writing it, so the model
        // knows what day it is publishing on. Without this it guesses, and cheerfully
        // opens with "Happy Monday!" on a post scheduled for a Friday.
        $scheduledAt = $this->schedulingService->getNextSlot($business, $platform);

        $recentPosts = $this->recentPostsForPlatform($business, $platform);

        $systemPrompt = $this->buildSystemPrompt($business, $platform, $platformRules);
        $userPrompt   = $this->buildUserPrompt($brief, $businessContext, $platform, $scheduledAt, $recentPosts);

        $startTime = microtime(true);

        $response = $this->callAnthropic([
            'model'      => self::MODEL,
            'max_tokens' => $platform === 'tiktok' ? 600 : 400,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ], $platform);

        $durationMs = (microtime(true) - $startTime) * 1000;

        if (! $response) {
            return null;
        }

        $body       = $response->json();
        $rawContent = $body['content'][0]['text'] ?? '';
        $usage      = $body['usage'] ?? [];

        $inputTokens  = $usage['input_tokens']  ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $parsed = $this->extractJson($rawContent);

        if (isset($parsed['content']) && is_string($parsed['content'])) {
            $parsed['content'] = $this->sanitiseContent($parsed['content']);
        }

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
     * Enforce house style on generated copy.
     *
     * The system prompt already forbids em dashes, and the model ignores it often
     * enough that a published post carried "hosting—the lot". A prompt rule the
     * model can quietly disregard is not a guarantee, so we strip them in code.
     * Only ever applied to AI output — a user's own edit is published verbatim.
     */
    private function sanitiseContent(string $content): string
    {
        // Non-breaking spaces come back from the model and render as stray
        // characters once the post reaches a platform.
        $content = str_replace(["\u{00A0}", "\u{200B}"], [' ', ''], $content);

        // Em and en dashes become the comma the sentence wanted in the first place.
        // Any spacing around the dash is absorbed, so "a — b" and "a—b" both give "a, b".
        $content = preg_replace('/\s*[\x{2014}\x{2013}]\s*/u', ', ', $content);

        // A dash directly after existing punctuation would otherwise double it up.
        $content = preg_replace('/([,;:])\s*,\s*/u', '$1 ', $content);
        $content = preg_replace('/\s+([,.!?])/u', '$1', $content);

        // Collapse any run of spaces or tabs the replacements left behind, without
        // touching newlines — paragraph breaks matter on Facebook and LinkedIn.
        $content = preg_replace('/[ \t]{2,}/u', ' ', $content);

        return trim($content);
    }

    /**
     * Call the Anthropic messages API, retrying transient failures.
     *
     * A single 529 "Overloaded" used to drop that platform's post for the day
     * with nothing surfaced to the user — the caller just logged and returned
     * null. Overload and rate-limit responses are routine and worth waiting out;
     * a 400 or 401 is our fault and retrying only wastes time.
     */
    private function callAnthropic(array $payload, string $platform): ?\Illuminate\Http\Client\Response
    {
        $delaysMs = [1000, 4000, 10000];
        $lastResponse = null;

        foreach (array_merge([0], $delaysMs) as $attempt => $delayMs) {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                $lastResponse = Http::withHeaders([
                    'x-api-key'         => config('services.anthropic.key'),
                    'anthropic-version' => config('services.anthropic.version'),
                    'content-type'      => 'application/json',
                ])->timeout(60)->post(config('services.anthropic.base_url').'/messages', $payload);
            } catch (\Throwable $e) {
                Log::warning("ContentGenerationService: Anthropic request failed for {$platform}", [
                    'attempt' => $attempt + 1,
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }

            if ($lastResponse->successful()) {
                if ($attempt > 0) {
                    Log::info("ContentGenerationService: Anthropic succeeded for {$platform} on attempt ".($attempt + 1));
                }
                return $lastResponse;
            }

            $status = $lastResponse->status();
            $retryable = $status === 429 || $status >= 500;

            Log::warning("ContentGenerationService: Anthropic API error for {$platform}", [
                'status'    => $status,
                'attempt'   => $attempt + 1,
                'retryable' => $retryable,
                'body'      => $lastResponse->body(),
            ]);

            if (! $retryable) {
                return null;
            }
        }

        Log::error("ContentGenerationService: Anthropic gave up for {$platform} after retries", [
            'status' => $lastResponse?->status(),
        ]);

        return null;
    }

    /**
     * The most recent posts we already wrote for this platform.
     *
     * Fed to the model so it can avoid repeating itself. Generation is otherwise
     * stateless: the same brief over the same source data produces near-identical
     * posts every run, which is exactly what happened here.
     *
     * @return string[]
     */
    private function recentPostsForPlatform(Business $business, string $platform, int $limit = 5): array
    {
        return Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', [
                Post::STATUS_PENDING,
                Post::STATUS_APPROVED,
                Post::STATUS_SCHEDULED,
                Post::STATUS_DISPATCHING,
                Post::STATUS_POSTED,
            ])
            ->latest('created_at')
            ->limit($limit)
            ->pluck('content')
            ->filter()
            ->values()
            ->all();
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
    private function buildUserPrompt(
        ContentBrief $brief,
        array $context,
        string $platform,
        ?\DateTimeInterface $scheduledAt = null,
        array $recentPosts = []
    ): string {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
Create a {$platform} post based on the following brief and business context.

{$this->getPublishingDatePrompt($scheduledAt)}

BRIEF:
Theme: {$brief->theme}
Key messages to communicate: {$brief->key_messages}
Tone notes: {$brief->tone_notes}
Source type: {$brief->source_type}

BUSINESS CONTEXT:
{$contextStr}

{$this->getReferenceDataPrompt($brief)}

{$this->getRecentPostsPrompt($recentPosts)}

Generate a post that feels native to {$platform} — not like it was copied from another platform.
The post should naturally reflect the brief theme whilst sounding completely authentic.
PROMPT;
    }

    /**
     * Tell the model when this post actually goes out.
     *
     * Generation is otherwise date-blind, so any reference to a day was a guess —
     * which is how a post scheduled for a Friday opened with "Happy Monday!".
     */
    private function getPublishingDatePrompt(?\DateTimeInterface $scheduledAt): string
    {
        if (! $scheduledAt) {
            return 'PUBLISHING DATE: unknown. Do not reference any day of the week, date or season.';
        }

        $when = \Illuminate\Support\Carbon::instance(
            $scheduledAt instanceof \DateTimeImmutable ? \DateTime::createFromImmutable($scheduledAt) : $scheduledAt
        );

        return 'PUBLISHING DATE: this post goes live on '.$when->format('l j F Y').' at '.$when->format('H:i')
            .". Any reference to the day, date or season must match that exactly. Do not mention the day at all unless it genuinely adds something.";
    }

    /**
     * Show the model what we have already published so it stops repeating itself.
     *
     * @param  string[]  $recentPosts
     */
    private function getRecentPostsPrompt(array $recentPosts): string
    {
        if (empty($recentPosts)) {
            return '';
        }

        $list = implode("\n\n", array_map(
            fn ($content, $i) => ($i + 1).'. '.trim($content),
            $recentPosts,
            array_keys($recentPosts)
        ));

        return <<<PROMPT
ALREADY PUBLISHED FOR THIS PLATFORM — DO NOT REPEAT THESE:
{$list}

The new post must be clearly different from every one of the above. Do not reuse their
opening line, structure, statistics, or turns of phrase. If the brief pushes you toward
the same angle, deliberately pick a different one: a specific service, a customer
problem, a question, a piece of practical advice.
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
