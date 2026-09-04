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

    /**
     * Statuses that mean a post exists as far as cadence and repetition go.
     *
     * Rejected and failed posts are deliberately absent: neither reached an
     * audience, so neither should hold the next one back.
     */
    private const LIVE_STATUSES = [
        Post::STATUS_PENDING,
        Post::STATUS_APPROVED,
        Post::STATUS_SCHEDULED,
        Post::STATUS_DISPATCHING,
        Post::STATUS_POSTED,
    ];

    /**
     * A platform gets at most one post every this many days.
     *
     * Generation previously wrote to every connected platform on every daily run
     * until the weekly target was met, so a week of content landed in the first
     * few days and read as relentless. The spacing itself is enforced by
     * SchedulingService, which will not place a slot within 48 hours of another
     * post on the same platform. This constant only caps how many posts a week
     * can hold, so we never generate one the scheduler would push past the
     * horizon and we would then discard.
     */
    private const MIN_DAYS_BETWEEN_POSTS = 2;

    /** Matches BusinessSetting::getPostsPerWeekForPlatform's own fallback. */
    private const DEFAULT_POSTS_PER_WEEK = 3;

    /**
     * How far ahead the schedule is kept full.
     *
     * Every run tops each platform back up to a full week of scheduled posts, so
     * a user opening the app on any day sees the week ahead, can sense check the
     * lot in one sitting and then leave it alone. Generating one post a day gave
     * them a day or two of visibility and no way to review a week at a time.
     */
    private const SCHEDULE_HORIZON_DAYS = 7;

    /**
     * The rotation of angles a post can take, walked in order per platform.
     *
     * Left to itself the model wrote the same "here is what we do" update every
     * time and opened on the season, because the season was the only thing that
     * changed between runs. Naming one concrete angle per post, and advancing the
     * cursor every time, forces genuinely different content out of the same brief.
     * Review and website quotes appear three times each, so roughly half of all
     * posts carry a real customer or website voice rather than a paraphrase of one.
     */
    private const ANGLE_ROTATION = [
        'review_quote',
        'service_spotlight',
        'website_quote',
        'customer_problem',
        'review_quote',
        'practical_tip',
        'website_quote',
        'local_angle',
        'review_quote',
        'behind_the_scenes',
        'website_quote',
        'faq',
    ];

    /** Angles that are pointless without source material to quote. */
    private const QUOTE_ANGLES = ['review_quote', 'website_quote'];

    /** Platforms where a bare URL in the body earns the characters it costs. */
    private const LINK_FRIENDLY_PLATFORMS = ['facebook', 'linkedin', 'google_business_profile'];

    /** What each angle asks the model to actually write. */
    private const ANGLE_BRIEFS = [
        'review_quote' =>
            'Build the post around ONE customer review, quoted word for word inside quotation marks. '
            .'Introduce the quote in the business\'s own voice, credit the reviewer by first name only, '
            .'and thank them warmly and specifically. If the review is long, pick the single most telling '
            .'sentence or two and quote exactly that rather than the lot.',
        'website_quote' =>
            'Build the post around ONE line lifted verbatim from the business\'s own website, quoted word '
            .'for word inside quotation marks. Then say what it means in practice for a customer: an example, '
            .'a consequence, something concrete that the line on its own does not tell them.',
        'service_spotlight' =>
            'Take ONE specific service and go deep on it. What it involves, who it is for, what is different '
            .'for the customer afterwards. Not a list of everything the business does.',
        'customer_problem' =>
            'Open on a specific problem a real customer in this industry actually has. Make it recognisable '
            .'and concrete, then show how the business deals with it.',
        'practical_tip' =>
            'Give away one genuinely useful piece of advice a reader could act on today, even if they never '
            .'buy anything. No teaser, no withheld punchline.',
        'local_angle' =>
            'Write about the area the business serves: somewhere it works, something specific about local '
            .'customers or local conditions. Only use place names that appear in the business context.',
        'behind_the_scenes' =>
            'Show how the work actually gets done: the process, the kit, the checks, the part customers never '
            .'see. Specific and unglamorous beats polished.',
        'faq' =>
            'Answer one real question customers ask. State the question, then answer it plainly and completely.',
    ];

    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly LinkShortenerService $linkShortener
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

        $horizonEnd = now()->addDays(self::SCHEDULE_HORIZON_DAYS);

        foreach ($platforms as $platform) {
            $needed = $this->postsNeededForHorizon($business, $platform);

            if ($needed < 1) {
                Log::info("ContentGenerationService: Skipping {$platform} - the week ahead is already full", [
                    'business_id' => $business->id,
                ]);
                continue;
            }

            for ($i = 0; $i < $needed; $i++) {
                // Work the slot out first. It costs nothing, and if the next free
                // one falls outside the week we want to know before paying for a
                // post that would sit beyond the horizon the user is reviewing.
                $slot = $this->schedulingService->getNextSlot($business, $platform);

                if ($slot->greaterThan($horizonEnd)) {
                    Log::info("ContentGenerationService: Stopping {$platform} - next free slot is beyond the horizon", [
                        'business_id' => $business->id,
                        'slot'        => $slot->toIso8601String(),
                    ]);
                    break;
                }

                try {
                    $post = $this->generateForPlatform($business, $brief, $platform, $businessContext, $slot);
                } catch (\Throwable $e) {
                    Log::error("ContentGenerationService: Failed to generate for {$platform}", [
                        'business_id' => $business->id,
                        'brief_id' => $brief->id,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                if (! $post) {
                    // The API gave up after its retries. Trying the remaining
                    // posts now would just burn the same failure several times.
                    break;
                }

                $generatedPosts[] = $post;
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
     * How many more posts this platform needs to fill the week ahead.
     *
     * Measured against what is already scheduled in the window rather than what
     * was created this week, because the user is looking at a calendar, not a
     * changelog: a post written on Sunday for next Thursday fills Thursday. That
     * also makes the top-up self-correcting, so a run that failed halfway just
     * picks up the shortfall next time.
     *
     * A target of 0 switches a platform off without disconnecting it.
     */
    private function postsNeededForHorizon(Business $business, string $platform): int
    {
        $settings = $business->settings;
        $target = $settings
            ? $settings->getPostsPerWeekForPlatform($platform)
            : self::DEFAULT_POSTS_PER_WEEK;

        // The 48 hour spacing caps what a week can physically hold, so a setting
        // of 10 a week would otherwise have us generating posts the scheduler
        // then pushes past the horizon and we discard.
        $target = min($target, $this->maxPostsInHorizon());

        if ($target <= 0) {
            return 0;
        }

        $scheduled = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', self::LIVE_STATUSES)
            ->whereBetween('scheduled_at', [now(), now()->addDays(self::SCHEDULE_HORIZON_DAYS)])
            ->count();

        return max(0, $target - $scheduled);
    }

    /** Most posts that fit in the horizon at the minimum spacing. */
    private function maxPostsInHorizon(): int
    {
        return intdiv(self::SCHEDULE_HORIZON_DAYS, self::MIN_DAYS_BETWEEN_POSTS) + 1;
    }

    /**
     * Generate a post for a single platform using Claude.
     */
    private function generateForPlatform(
        Business $business,
        ContentBrief $brief,
        string $platform,
        array $businessContext,
        \Carbon\Carbon $scheduledAt
    ): ?Post {
        $platformRules = self::PLATFORM_RULES[$platform] ?? null;
        if (! $platformRules) {
            return null;
        }

        // The slot is worked out by the caller and passed in, so the model knows
        // what day it is publishing on before it writes a word. Without it the
        // model guesses, and cheerfully opens with "Happy Monday!" on a Friday.
        $recentPosts   = $this->recentPostsForPlatform($business, $platform);
        $recentOpeners = $this->recentOpeningLines($business);
        $angle         = $this->selectAngle($business, $platform, $businessContext);

        // Shorten before generating, not after. The model has to write the URL
        // into the post itself, so swapping it afterwards would mean editing
        // generated copy and hoping the sentence still reads.
        $shortLink = $this->trackedReviewsLink($businessContext, $angle, $platform);

        if ($shortLink) {
            $businessContext['reviews_url'] = $shortLink['short_url'];
        }

        $systemPrompt = $this->buildSystemPrompt($business, $platform, $platformRules);
        $userPrompt   = $this->buildUserPrompt(
            $brief,
            $businessContext,
            $platform,
            $scheduledAt,
            $recentPosts,
            $recentOpeners,
            $angle
        );

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
            // Fully automatic posts go straight to scheduled. They used to be
            // written as approved and then scheduled in a second write, which
            // left approved_at null and made an auto-approved post look, in the
            // UI and in the database, like nobody had ever approved it.
            'status'            => $requiresApproval ? Post::STATUS_PENDING : Post::STATUS_SCHEDULED,
            'approved_at'       => $requiresApproval ? null : now(),
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
                // Recorded so the next run can rotate past this angle and avoid
                // quoting the same review or website line twice in a row.
                'angle'             => $angle['angle'],
                'quoted_source_id'  => $angle['source_id'],
                // Kept so click counts can be read back off LinkVine per post.
                'short_link'        => $shortLink,
            ],
        ]);

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
            ->whereIn('status', self::LIVE_STATUSES)
            ->latest('created_at')
            ->limit($limit)
            ->pluck('content')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Opening lines already used, across every platform.
     *
     * The same-platform history alone did not stop "It's early September" turning
     * up on Facebook, LinkedIn and GBP in the same week: each platform only ever
     * saw its own back catalogue, and the season was the one thing every platform
     * reached for. Openers are pooled across all of them for that reason.
     *
     * @return string[]
     */
    private function recentOpeningLines(Business $business, int $limit = 10): array
    {
        return Post::where('business_id', $business->id)
            ->whereIn('status', self::LIVE_STATUSES)
            ->latest('created_at')
            ->limit($limit)
            ->pluck('content')
            ->map(function (?string $content) {
                $firstLine = trim(strtok(trim((string) $content), "\n") ?: '');

                // Long opening paragraphs are cut back to the first sentence, which
                // is the part that actually keeps getting reused.
                if (preg_match('/^.{20,200}?[.!?]/u', $firstLine, $m)) {
                    return trim($m[0]);
                }

                return $firstLine;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Choose the angle for the next post on a platform, plus the exact piece of
     * source material it should quote.
     *
     * The cursor is the number of posts already written for the platform, so the
     * rotation advances by one every run and never sits still. An angle that needs
     * source material we do not have (no reviews scraped, no website copy) is
     * skipped rather than attempted, because the model asked for a quote it has
     * not been given will simply invent one.
     *
     * @param  array<string, mixed>  $context
     * @return array{angle: string, brief: string, source: array<string, mixed>|null, source_id: string|null}
     */
    private function selectAngle(Business $business, string $platform, array $context): array
    {
        $history  = $this->recentAngleHistory($business, $platform);
        $rotation = self::ANGLE_ROTATION;
        $size     = count($rotation);

        for ($i = 0; $i < $size; $i++) {
            $angle  = $rotation[($history['count'] + $i) % $size];
            $source = $this->sourceForAngle($angle, $context, $history['source_ids']);

            if (in_array($angle, self::QUOTE_ANGLES, true) && ! $source) {
                continue;
            }

            return [
                'angle'     => $angle,
                'brief'     => self::ANGLE_BRIEFS[$angle],
                'source'    => $source,
                'source_id' => $source['id'] ?? null,
            ];
        }

        // Every angle in the rotation needed material we do not have, which can
        // only happen if the whole rotation is quote angles. Fall back to one that
        // never needs a source.
        return [
            'angle'     => 'service_spotlight',
            'brief'     => self::ANGLE_BRIEFS['service_spotlight'],
            'source'    => null,
            'source_id' => null,
        ];
    }

    /**
     * How many posts this platform has had, and what has been quoted lately.
     *
     * The count is per platform, because each platform walks its own rotation.
     * The quoted source ids are business-wide, because a reader following two of
     * these accounts sees both. Scoped per platform, every platform independently
     * picked the newest unquoted review and a week's posts opened with the same
     * customer saying the same thing on Facebook, LinkedIn and GBP at once.
     *
     * The lookback is deliberately generous: it has to span every platform's
     * share of a week, not just one platform's.
     *
     * @return array{count: int, source_ids: string[]}
     */
    private function recentAngleHistory(Business $business, string $platform, int $lookback = 20): array
    {
        $count = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', self::LIVE_STATUSES)
            ->count();

        $sourceIds = Post::where('business_id', $business->id)
            ->whereIn('status', self::LIVE_STATUSES)
            ->latest('created_at')
            ->limit($lookback)
            ->pluck('ai_metadata')
            ->map(fn ($meta) => is_array($meta) ? ($meta['quoted_source_id'] ?? null) : null)
            ->filter()
            ->values()
            ->all();

        return [
            'count'      => $count,
            'source_ids' => $sourceIds,
        ];
    }

    /**
     * Pick the item this angle should quote, preferring one we have not used lately.
     *
     * @param  array<string, mixed>  $context
     * @param  string[]  $usedSourceIds
     * @return array<string, mixed>|null
     */
    private function sourceForAngle(string $angle, array $context, array $usedSourceIds): ?array
    {
        $pool = match ($angle) {
            'review_quote'  => $context['reviews'] ?? [],
            'website_quote' => $context['website_excerpts'] ?? [],
            default         => [],
        };

        if (empty($pool)) {
            return null;
        }

        // Without this the newest five star review gets quoted every single time
        // the rotation comes back round to it.
        foreach ($pool as $item) {
            if (! in_array($item['id'] ?? null, $usedSourceIds, true)) {
                return $item;
            }
        }

        return $pool[0];
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

BANNED OPENINGS — these are the reason every post reads the same, so none of them, ever:
- The season, month, weather or time of year. "It's early September", "As autumn draws in",
  "With summer behind us", "This time of year" and every variation are forbidden anywhere
  in the post, not just the first line.
- Generic scene-setting about the industry or about business in general.
- "Looking for...", "Ever wondered...", "Let's talk about...", "Here at {$business->name}...".
- Opening with the business name at all.
Start on something specific instead: a customer's words, a line from the website, a real
problem, a number, a named service.

QUOTING RULES:
- You may ONLY quote text that appears in SOURCE MATERIAL, and you must reproduce it word
  for word inside quotation marks. Do not tidy it, correct it, shorten it mid-sentence or
  change its punctuation. Quoting fewer sentences than you were given is fine; altering the
  ones you use is not.
- NEVER invent a quote, a reviewer, a testimonial or a statistic. If no source material is
  supplied, write the post without a quote.
- Credit a reviewer by first name only. Never use a surname or a full name.
- Every quotation mark in the post must belong to text you were given. If you are writing
  to an angle with no SOURCE MATERIAL, the post contains no quotes at all.
- No figures unless they appear in the material above: no percentages, no "we have helped
  X businesses", no growth numbers, no years in business you were not told. A number you
  cannot point at in the context is invented, however plausible it sounds.
- Spell every place name, business name and person's name exactly as it appears above. If a
  town is not named in the context, do not name one, and never guess at a local hashtag.

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
        array $recentPosts = [],
        array $recentOpeners = [],
        array $angle = []
    ): string {
        // The quotable material is rendered as its own block rather than left in
        // the JSON dump, so the model treats it as copy to lift rather than as
        // more background to paraphrase.
        $contextStr = json_encode(
            array_diff_key($context, array_flip(['reviews', 'website_excerpts'])),
            JSON_PRETTY_PRINT
        );

        return <<<PROMPT
Create a {$platform} post based on the following brief and business context.

{$this->getPublishingDatePrompt($scheduledAt)}

{$this->getAnglePrompt($angle, $context, $platform)}

BRIEF (background. Where the brief and the angle above pull in different directions, follow the angle):
Theme: {$brief->theme}
Key messages to communicate: {$brief->key_messages}
Tone notes: {$brief->tone_notes}
Source type: {$brief->source_type}

BUSINESS CONTEXT:
{$contextStr}

{$this->getReferenceDataPrompt($brief)}

{$this->getRecentPostsPrompt($recentPosts)}

{$this->getRecentOpenersPrompt($recentOpeners)}

Generate a post that feels native to {$platform} — not like it was copied from another platform.
Write to the angle above. It is the point of the post, not a suggestion.
PROMPT;
    }

    /**
     * A short, per-post link to the reviews page, when this post will carry one.
     *
     * Only review posts on platforms where a URL is worth its characters get one,
     * which keeps the number of links created to roughly one per platform per
     * week. A null here is not a failure worth stopping for: the caller keeps the
     * full URL, which is uglier but works.
     *
     * @param  array<string, mixed>  $context
     * @param  array{angle?: string}  $angle
     * @return array{id: int|null, short_url: string}|null
     */
    private function trackedReviewsLink(array $context, array $angle, string $platform): ?array
    {
        if (($angle['angle'] ?? null) !== 'review_quote') {
            return null;
        }

        if (empty($context['reviews_url']) || ! in_array($platform, self::LINK_FRIENDLY_PLATFORMS, true)) {
            return null;
        }

        return $this->linkShortener->shorten($context['reviews_url']);
    }

    /**
     * Spell out the angle for this post and hand over the exact text to quote.
     *
     * @param  array{angle?: string, brief?: string, source?: array<string, mixed>|null}  $angle
     * @param  array<string, mixed>  $context
     */
    private function getAnglePrompt(array $angle, array $context, string $platform): string
    {
        if (empty($angle['angle'])) {
            return '';
        }

        $lines = [
            'ANGLE FOR THIS POST: '.$angle['angle'],
            $angle['brief'],
        ];

        $source = $angle['source'] ?? null;

        if ($source && $angle['angle'] === 'review_quote') {
            $author = $source['author'] ?? null;

            $lines[] = '';
            $lines[] = 'SOURCE MATERIAL — the review to quote:';
            $lines[] = 'Reviewer first name: '.($author
                ?: 'not recorded. Write "one of our customers" and do not invent a name.');

            if (! empty($source['rating'])) {
                $lines[] = 'Rating given: '.$source['rating'].' out of 5';
            }

            $lines[] = 'Review text, to be quoted word for word: "'.$source['quote'].'"';

            // A URL costs characters Twitter does not have and is dead text on
            // Instagram, so it only goes where a reader can actually follow it.
            if (! empty($context['reviews_url']) && in_array($platform, self::LINK_FRIENDLY_PLATFORMS, true)) {
                $lines[] = 'Finish by inviting readers to see more reviews at: '.$context['reviews_url'];
                $lines[] = 'Use that URL exactly as written. Do not shorten or reword it.';
            }
        }

        if ($source && $angle['angle'] === 'website_quote') {
            $lines[] = '';
            $lines[] = 'SOURCE MATERIAL — the website line to quote:';

            if (! empty($source['page'])) {
                $lines[] = 'Taken from: '.$source['page'];
            }

            $lines[] = 'Website text, to be quoted word for word: "'.$source['quote'].'"';
        }

        return implode("\n", $lines);
    }

    /**
     * Opening lines already in circulation, so the model picks a different one.
     *
     * @param  string[]  $recentOpeners
     */
    private function getRecentOpenersPrompt(array $recentOpeners): string
    {
        if (empty($recentOpeners)) {
            return '';
        }

        $list = implode("\n", array_map(
            fn ($line, $i) => ($i + 1).'. '.$line,
            $recentOpeners,
            array_keys($recentOpeners)
        ));

        return <<<PROMPT
OPENING LINES ALREADY USED ACROSS THIS BUSINESS'S POSTS:
{$list}

Do not open with any of these, a rephrasing of one, or anything built on the same idea.
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

These are here to be avoided, not mined. They are NOT source material: do not lift a
quote, a customer name, a phrase or a figure out of them, even though the quotes in them
are real. A quote that has already run this week has already been read.

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
                'url'         => $websiteSource->source_url,
                'page_title'  => $data['page_title'] ?? null,
                'description' => $data['meta_description'] ?? null,
                'services'    => array_slice($data['services'] ?? [], 0, 5),
                'key_phrases' => array_slice($data['key_phrases'] ?? [], 0, 10),
            ];
        }

        $context['website_excerpts'] = $this->websiteExcerpts($websiteSource);
        $context['reviews'] = $this->quotableReviews($business);

        if ($business->google_reviews_url) {
            $context['reviews_url'] = $business->google_reviews_url;
        }

        return $context;
    }

    /**
     * Real reviews, whole and attributable, for the model to quote.
     *
     * The context used to carry a 200 character slice of review text and a
     * sentiment float, with the reviewer's name thrown away entirely, so the best
     * the model could manage was "our customers love us". Quoting someone by name
     * and thanking them needs the name, the rating, and enough of the review to
     * find a good line inside.
     *
     * @return array<int, array{id: string, author: string|null, rating: int|null, quote: string}>
     */
    private function quotableReviews(Business $business, int $limit = 8): array
    {
        // Until now nothing read this setting, so turning it off did nothing. It
        // matters as soon as posts start quoting customers by name.
        if ($business->settings && ! $business->settings->include_review_content) {
            return [];
        }

        return $business->contentSources()
            ->where('type', ContentSource::TYPE_REVIEW)
            ->latest('scraped_at')
            ->limit($limit * 3)
            ->get()
            // Review rows created before scraping deduplicated on review text are
            // still in the table, several copies of the same customer each. Left
            // in, they would crowd out every other reviewer in the rotation.
            ->unique('raw_data')
            ->take($limit)
            ->map(function (ContentSource $source) {
                $quote = $this->trimToSentence(trim((string) $source->raw_data), 450);

                if ($quote === '') {
                    return null;
                }

                $structured = $source->structured_data ?? [];

                return [
                    'id'     => $source->id,
                    'author' => $this->firstName($structured['author'] ?? null),
                    'rating' => $structured['rating'] ?? null,
                    'quote'  => $quote,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Lines lifted verbatim from the business's own website, ready to be quoted.
     *
     * Scrapes made since page-level text was stored keep each page separate, so a
     * quote can be credited to the page it came from. Older rows only have the
     * concatenated body text, which still yields usable sentences, just without
     * the attribution.
     *
     * @return array<int, array{id: string, page: string|null, url: string|null, quote: string}>
     */
    private function websiteExcerpts(?ContentSource $source, int $limit = 8): array
    {
        if (! $source) {
            return [];
        }

        $structured = $source->structured_data ?? [];

        $pages = $structured['page_text'] ?? [[
            'url'   => $source->source_url,
            'title' => $structured['page_title'] ?? null,
            'text'  => (string) $source->raw_data,
        ]];

        $pages = array_map(fn (array $page) => $page + [
            'sentences' => $this->quotableSentences((string) ($page['text'] ?? '')),
        ], $pages);

        // Round-robin rather than page-by-page, so a long homepage cannot fill the
        // whole allowance and leave the services page unquoted.
        $excerpts = [];
        for ($depth = 0; count($excerpts) < $limit; $depth++) {
            $foundAtThisDepth = false;

            foreach ($pages as $page) {
                $sentences = $page['sentences'] ?? [];

                if (! isset($sentences[$depth])) {
                    continue;
                }

                $foundAtThisDepth = true;
                $excerpts[] = [
                    // Content sources have no per-sentence id, so hash the text.
                    // It only has to be stable enough to spot a repeat quote.
                    'id'    => 'web:'.substr(sha1($sentences[$depth]), 0, 12),
                    'page'  => $page['title'] ?? null,
                    'url'   => $page['url'] ?? $source->source_url,
                    'quote' => $sentences[$depth],
                ];

                if (count($excerpts) >= $limit) {
                    break;
                }
            }

            if (! $foundAtThisDepth) {
                break;
            }
        }

        return $excerpts;
    }

    /**
     * Split scraped page text into sentences that stand up on their own as a quote.
     *
     * @return string[]
     */
    private function quotableSentences(string $text, int $limit = 8): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));

        if ($text === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $keep = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            $length = mb_strlen($sentence);

            // Too short to say anything, too long to drop into a post.
            if ($length < 45 || $length > 220) {
                continue;
            }

            // Must read as a sentence, not a heading or a fragment of a list.
            if (! preg_match('/[.!?]$/u', $sentence) || str_word_count($sentence) < 8) {
                continue;
            }

            // Contact details, legal furniture and cookie notices are not marketing copy.
            if (preg_match('/@|https?:|©|\bcookies?\b|\bprivacy policy\b|\ball rights reserved\b|\bterms (and|&) conditions\b/iu', $sentence)) {
                continue;
            }

            $keep[$sentence] = $sentence;

            if (count($keep) >= $limit) {
                break;
            }
        }

        return array_values($keep);
    }

    /**
     * A reviewer's first name, or null when there is nothing safe to use.
     *
     * Google hands back a display name that may be a full name, a single name or
     * the literal "Anonymous". Only the first name ever goes in a post, and an
     * unusable one returns null so the prompt can tell the model to write around
     * it rather than guess.
     */
    private function firstName(?string $displayName): ?string
    {
        $name = trim((string) $displayName);

        if ($name === '' || strcasecmp($name, 'Anonymous') === 0) {
            return null;
        }

        $first = trim(strtok($name, " \t") ?: '', " .,");

        // Initials and handles read as a mistake when a post thanks them by name.
        if (mb_strlen($first) < 2 || ! preg_match('/^\pL[\pL\pM\x27-]*$/u', $first)) {
            return null;
        }

        return $first;
    }

    /**
     * Cut text back to the last complete sentence that fits inside $maxChars.
     *
     * A quote sliced mid-word reads as a mistake, and gives the model licence to
     * finish the sentence itself, which is how invented quotes start.
     */
    private function trimToSentence(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $window = mb_substr($text, 0, $maxChars);

        // Greedy, so this lands on the last sentence that fits. A short complete
        // sentence is still a usable quote, so there is no minimum length here:
        // rejecting one only sends us to the fragment fallback below, which is
        // the worse outcome in every case.
        if (preg_match('/^.*[.!?]/us', $window, $m)) {
            return trim($m[0]);
        }

        // Nothing in the window ends a sentence, so the last whole word is all
        // that is left.
        $lastSpace = mb_strrpos($window, ' ');

        return trim($lastSpace === false ? $window : mb_substr($window, 0, $lastSpace));
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
        // Background only. This used to say "use this directly", which put a
        // second copy of a review in front of the model alongside the one the
        // angle picked, and it would sometimes quote the wrong one. SOURCE
        // MATERIAL is now the only text anything may be quoted from.
        return "REFERENCE DATA (background, not quotable):\n{$data}";
    }
}
