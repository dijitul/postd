<?php

namespace App\Modules\Content\Services;

use App\Models\Business;
use App\Models\ContentBrief;
use App\Models\ContentSource;
use App\Models\Post;
use App\Modules\Billing\Services\EntitlementService;
use App\Modules\Billing\Services\Entitlements;
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
            'max_hashtags' => 2,
            'special' => 'End with a clear call-to-action. Make it feel like a friend sharing something useful.',
        ],
        'twitter' => [
            'format' => 'Punchy, opinionated tweet',
            'tone' => 'Direct, witty, confident',
            'max_chars' => 260,
            'emojis' => 'sparingly',
            'hashtags' => '1-2 hashtags maximum',
            'max_hashtags' => 2,
            'special' => 'Either a bold statement or a question that sparks engagement. No fluff.',
        ],
        'linkedin' => [
            'format' => 'Professional insight post',
            'tone' => 'Expert, thoughtful, value-led',
            'min_words' => 120,
            'max_words' => 300,
            'emojis' => false,
            'hashtags' => '2-3 professional hashtags at the end',
            'max_hashtags' => 3,
            'special' => 'Lead with an insight or observation. Avoid corporate speak. Be genuinely useful.',
        ],
        'google_business_profile' => [
            'format' => 'Business update post',
            'tone' => 'Clear, helpful, professional',
            'min_words' => 60,
            'max_words' => 150,
            'emojis' => false,
            'hashtags' => 'none',
            'max_hashtags' => 0,
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
        'whats_new' =>
            'The business has just added the page below to its website. Tell readers what is new and why it is '
            .'worth their time, using only what the page says. Lead with the most useful specific from it, not '
            .'with the fact that something has been published.',
    ];

    /**
     * Which pages of the business's site each angle should draw its specifics from, best first.
     *
     * Only the two quote angles used to touch the website, so half of all posts
     * were written from a one-line description and a list of service names, and
     * read as generic as that sounds. Grounding every other angle in a real page
     * gives the model something specific to say. Quote angles are absent: they
     * already carry their own line from the site or a review.
     */
    private const ANGLE_PAGE_KINDS = [
        'service_spotlight' => ['service', 'other', 'home'],
        'customer_problem'  => ['service', 'article', 'faq'],
        'practical_tip'     => ['article', 'faq', 'service'],
        'local_angle'       => ['about', 'home', 'service'],
        'behind_the_scenes' => ['about', 'service', 'article'],
        'faq'               => ['faq', 'service', 'article'],
        'whats_new'         => ['article', 'service', 'other', 'faq', 'about'],
    ];

    /** Angles worth linking to the page they were drawn from, where the platform takes a link. */
    private const LINKED_PAGE_ANGLES = ['whats_new', 'service_spotlight'];

    /** A page first seen within this many days counts as new and gets its own post. */
    private const FRESH_PAGE_DAYS = 21;

    /**
     * Word-trigram overlap above which a draft counts as a rerun of an earlier post.
     *
     * Two genuinely different posts for the same business rarely share more
     * than a few percent of their three word runs; a reworded copy shares a
     * third or more.
     */
    private const MAX_SIMILARITY = 0.3;

    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly LinkShortenerService $linkShortener,
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Generate a full cascade of posts from a ContentBrief.
     * One brief → one platform-native post per connected platform.
     */
    public function generateFromBrief(ContentBrief $brief): array
    {
        $business = $brief->business()->with(['settings', 'activeSocialConnections'])->first();
        $entitlements = $this->entitlementService->forBusiness($business);

        // No plan (the trial ended, or a subscription lapsed): generation
        // pauses. Nothing already written is touched.
        if (! $entitlements->active) {
            Log::info("ContentGenerationService: Skipping business {$business->id} - no active plan");
            return [];
        }

        // Only the platforms the plan covers. A Local account that kept four
        // connections from its trial is written for on the first two only.
        $platforms = $entitlements->usablePlatforms($business->connectedPlatforms());

        if (empty($platforms)) {
            Log::warning("ContentGenerationService: No connected platforms for business {$business->id}");
            return [];
        }

        $businessContext = $this->buildBusinessContext($business, $brief);
        $generatedPosts = [];

        $horizonEnd = now()->addDays(self::SCHEDULE_HORIZON_DAYS);

        // X is capped in total during the trial, because every X post costs us.
        $twitterRemaining = $this->entitlementService->twitterPostsRemaining($business);

        foreach ($platforms as $platform) {
            $target = $this->postsPerWeekTarget($business, $platform, $entitlements);
            $needed = $this->postsNeededForHorizon($business, $platform, $target);

            if ($platform === 'twitter' && $twitterRemaining !== null && $needed > $twitterRemaining) {
                Log::info('ContentGenerationService: Holding X to the trial allowance', [
                    'business_id' => $business->id,
                    'remaining'   => $twitterRemaining,
                ]);
                $needed = $twitterRemaining;
            }

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
                $slot = $this->schedulingService->getNextSlot($business, $platform, $target);

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
    private function postsNeededForHorizon(Business $business, string $platform, int $target): int
    {
        // The minimum spacing caps what a week can physically hold, so a target
        // above it would have us generating posts the scheduler then pushes
        // past the horizon and we discard.
        $target = min($target, $this->maxPostsInHorizon($target));

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

    /**
     * The business's chosen posts a week for a platform, held to its plan.
     *
     * The saved setting is left alone, so a Growth customer who drops to
     * Local and back gets their 7 a week again; Local simply writes 3 in the
     * meantime.
     */
    private function postsPerWeekTarget(Business $business, string $platform, Entitlements $entitlements): int
    {
        $settings = $business->settings;
        $requested = $settings
            ? $settings->getPostsPerWeekForPlatform($platform)
            : self::DEFAULT_POSTS_PER_WEEK;

        return $entitlements->clampPostsPerWeek($platform, $requested);
    }

    /** Most posts that fit in the horizon at the spacing used for this cadence. */
    private function maxPostsInHorizon(int $postsPerWeek): int
    {
        $gap = SchedulingService::minGapMinutesFor($postsPerWeek);

        return intdiv(self::SCHEDULE_HORIZON_DAYS * 24 * 60, $gap) + 1;
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
        $hashtagGuard = new HashtagGuard($this->hashtagVocabulary($businessContext));
        $comparePosts = $this->postsToCompareAgainst($business);

        $inputTokens = 0;
        $outputTokens = 0;
        $costUsd = 0.0;
        $parsed = null;
        $similarity = 0.0;
        $removedHashtags = [];
        $messages = [['role' => 'user', 'content' => $userPrompt]];

        // One draft, plus one rewrite if the draft turns out to be a rerun of a
        // recent post. Telling the model what it already wrote only goes so far:
        // over enough weeks, the same brief drifts back to the same post.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = $this->callAnthropic([
                'model'      => self::MODEL,
                'max_tokens' => $this->maxTokensFor($platform, $platformRules),
                'system'     => $systemPrompt,
                'messages'   => $messages,
            ], $platform);

            if (! $response) {
                break;
            }

            $body = $response->json();
            $rawContent = $body['content'][0]['text'] ?? '';
            $usage = $body['usage'] ?? [];

            $callCost = $this->calculateCost(self::MODEL, $usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0);
            $inputTokens += $usage['input_tokens'] ?? 0;
            $outputTokens += $usage['output_tokens'] ?? 0;
            $costUsd += $callCost;
            $this->logAiCost($business, null, 'text_generation', self::MODEL, $usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0, $callCost);

            $draft = $this->extractJson($rawContent);

            if (! $draft || ! isset($draft['content']) || ! is_string($draft['content'])) {
                Log::error("ContentGenerationService: Bad response format for {$platform}", [
                    'raw' => $rawContent,
                ]);
                break;
            }

            $draft['content'] = $this->sanitiseContent($draft['content']);

            $checked = $hashtagGuard->clean($draft['content'], $platformRules['max_hashtags'] ?? 0);
            $draft['content'] = $checked['content'];
            $draft['hashtags'] = $checked['hashtags'];

            [$draftSimilarity, $closest] = $this->closestMatch($draft['content'], $comparePosts);

            // Keep whichever draft is less like what has already gone out.
            if (! $parsed || $draftSimilarity < $similarity) {
                $parsed = $draft;
                $similarity = $draftSimilarity;
                $removedHashtags = $checked['removed'];
            }

            if ($draftSimilarity < self::MAX_SIMILARITY) {
                break;
            }

            Log::info("ContentGenerationService: Draft too close to an earlier post on {$platform}, rewriting", [
                'business_id' => $business->id,
                'similarity'  => round($draftSimilarity, 2),
            ]);

            $messages[] = ['role' => 'assistant', 'content' => $rawContent];
            $messages[] = ['role' => 'user', 'content' => "That reads too much like a post this business has already published:\n\n"
                .$closest
                ."\n\nWrite a new post to the same angle that a reader who saw that one would find genuinely new: a "
                .'different opening, a different detail from the material, a different structure. Same JSON format.'];
        }

        $durationMs = (microtime(true) - $startTime) * 1000;

        if (! $parsed) {
            return null;
        }

        if ($removedHashtags) {
            Log::info("ContentGenerationService: Removed unsupported hashtags on {$platform}", [
                'business_id' => $business->id,
                'removed'     => $removedHashtags,
            ]);
        }

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
                // The website page the post was written from, so the next run
                // moves on to a different part of the site.
                'page_id'           => $angle['page_id'],
                'similarity'        => round($similarity, 3),
                'removed_hashtags'  => $removedHashtags,
                // Kept so click counts can be read back off LinkVine per post.
                'short_link'        => $shortLink,
            ],
        ]);

        // Queue image generation if the platform benefits from it, images are
        // enabled and the plan has one left this month. Once the allowance is
        // used the post simply goes out text-only; GeneratePostImageJob checks
        // again when it runs, since one run can queue several at once.
        if (config('services.openai_images.enabled')
            && $this->platformNeedsImage($platform)
            && ($business->settings?->generate_images ?? true)
            && $this->entitlementService->aiImagesRemaining($business) > 0) {
            \App\Modules\Content\Jobs\GeneratePostImageJob::dispatch($post, $parsed['image_prompt'] ?? null)
                ->onQueue('generation')
                ->delay(now()->addSeconds(5));
        }

        return $post;
    }

    /**
     * Token budget for one post, sized to what the platform is allowed to say.
     *
     * Every platform shared a 400 token ceiling, but LinkedIn is allowed 300
     * words, which is already about 400 tokens before the JSON wrapper, the
     * hashtags and the image prompt. The reply was cut off mid structure, failed
     * to parse and the post was dropped, so a LinkedIn slot went quietly missing
     * from roughly every other generated week.
     *
     * Two tokens a word is deliberately generous, and the 200 on top covers the
     * JSON scaffolding around the copy.
     */
    private function maxTokensFor(string $platform, array $rules): int
    {
        if (isset($rules['max_words'])) {
            return max(400, ($rules['max_words'] * 2) + 200);
        }

        // X is capped in characters, not words, and fits well inside this.
        return 400;
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
     * Everything a hashtag may legitimately be built from.
     *
     * Deliberately excludes the generated post itself: a wrong town in the body
     * must not vouch for the same wrong town in a hashtag.
     *
     * @param  array<string, mixed>  $context
     * @return string[]
     */
    private function hashtagVocabulary(array $context): array
    {
        $texts = [
            $context['business_name'] ?? '',
            $context['industry'] ?? '',
            $context['location'] ?? '',
            $context['description'] ?? '',
            $context['usp_notes'] ?? '',
        ];

        $website = $context['website_data'] ?? [];
        $texts[] = $website['page_title'] ?? '';
        $texts[] = $website['description'] ?? '';
        $texts = array_merge($texts, $website['services'] ?? [], $website['key_phrases'] ?? []);

        foreach ($context['website_pages'] ?? [] as $page) {
            $texts[] = ($page['title'] ?? '').' '.$page['text'];
        }

        foreach ($context['website_excerpts'] ?? [] as $excerpt) {
            $texts[] = $excerpt['quote'];
        }

        foreach ($context['reviews'] ?? [] as $review) {
            $texts[] = $review['quote'];
        }

        return array_filter(array_map('strval', $texts));
    }

    /**
     * Recent posts across every platform, for the duplicate check.
     *
     * @return string[]
     */
    private function postsToCompareAgainst(Business $business, int $limit = 20): array
    {
        return Post::where('business_id', $business->id)
            ->whereIn('status', self::LIVE_STATUSES)
            ->latest('created_at')
            ->limit($limit)
            ->pluck('content')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * How close a draft is to the most similar earlier post, and which post that is.
     *
     * Jaccard overlap of word trigrams: cheap, needs no model call, and catches
     * the failure we actually see, which is the same post lightly reworded.
     *
     * @param  string[]  $posts
     * @return array{0: float, 1: string|null}
     */
    private function closestMatch(string $draft, array $posts): array
    {
        $draftShingles = $this->shingles($draft);
        $best = 0.0;
        $closest = null;

        if ($draftShingles === []) {
            return [$best, $closest];
        }

        foreach ($posts as $post) {
            $shingles = $this->shingles($post);

            if ($shingles === []) {
                continue;
            }

            $overlap = count(array_intersect_key($draftShingles, $shingles))
                / count($draftShingles + $shingles);

            if ($overlap > $best) {
                $best = $overlap;
                $closest = $post;
            }
        }

        return [$best, $closest];
    }

    /** @return array<string, true> */
    private function shingles(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $shingles = [];

        for ($i = 0; $i + 2 < count($words); $i++) {
            $shingles[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
        }

        return $shingles;
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
        $history = $this->recentAngleHistory($business, $platform);
        $pages   = $context['website_pages'] ?? [];

        // A page the business has just published jumps the queue, once per
        // platform. It is the one thing on the site nobody has read yet, and
        // left to the rotation it could wait a fortnight for its turn.
        foreach ($pages as $page) {
            if ($page['fresh'] && ! in_array($page['id'], $history['featured_page_ids'], true)) {
                return $this->angle('whats_new', null, $page);
            }
        }

        $rotation = self::ANGLE_ROTATION;
        $size     = count($rotation);

        for ($i = 0; $i < $size; $i++) {
            $angle  = $rotation[($history['count'] + $i) % $size];
            $source = $this->sourceForAngle($angle, $context, $history['source_ids']);

            if (in_array($angle, self::QUOTE_ANGLES, true) && ! $source) {
                continue;
            }

            return $this->angle($angle, $source, $this->pageForAngle($angle, $pages, $history['page_ids']));
        }

        // Every angle in the rotation needed material we do not have, which can
        // only happen if the whole rotation is quote angles. Fall back to one that
        // never needs a source.
        return $this->angle('service_spotlight', null, $this->pageForAngle('service_spotlight', $pages, $history['page_ids']));
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @param  array<string, mixed>|null  $page
     * @return array{angle: string, brief: string, source: array<string, mixed>|null, source_id: string|null, page: array<string, mixed>|null, page_id: string|null}
     */
    private function angle(string $angle, ?array $source, ?array $page): array
    {
        return [
            'angle'     => $angle,
            'brief'     => self::ANGLE_BRIEFS[$angle],
            'source'    => $source,
            'source_id' => $source['id'] ?? null,
            'page'      => $page,
            'page_id'   => $page['id'] ?? null,
        ];
    }

    /**
     * The page of the business's site this angle should be written from.
     *
     * Prefers the kinds of page that suit the angle, and within those the page
     * gone longest without being used, so over a few weeks the posts work
     * through the whole site rather than the homepage every time.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @param  string[]  $usedPageIds  Newest first.
     * @return array<string, mixed>|null
     */
    private function pageForAngle(string $angle, array $pages, array $usedPageIds): ?array
    {
        $kinds = self::ANGLE_PAGE_KINDS[$angle] ?? null;

        if (! $kinds || empty($pages)) {
            return null;
        }

        $candidates = array_values(array_filter($pages, fn ($p) => in_array($p['kind'], $kinds, true))) ?: $pages;

        usort($candidates, function (array $a, array $b) use ($kinds, $usedPageIds) {
            // Never used beats used; after that, used longest ago wins.
            $ageA = array_search($a['id'], $usedPageIds, true);
            $ageB = array_search($b['id'], $usedPageIds, true);
            $ageA = $ageA === false ? PHP_INT_MAX : $ageA;
            $ageB = $ageB === false ? PHP_INT_MAX : $ageB;

            if ($ageA !== $ageB) {
                return $ageB <=> $ageA;
            }

            return array_search($a['kind'], $kinds, true) <=> array_search($b['kind'], $kinds, true);
        });

        return $candidates[0];
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
     * @return array{count: int, source_ids: string[], page_ids: string[], featured_page_ids: string[]}
     */
    private function recentAngleHistory(Business $business, string $platform, int $lookback = 20): array
    {
        $count = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', self::LIVE_STATUSES)
            ->count();

        $recentMeta = Post::where('business_id', $business->id)
            ->whereIn('status', self::LIVE_STATUSES)
            ->latest('created_at')
            ->limit($lookback)
            ->pluck('ai_metadata')
            ->filter(fn ($meta) => is_array($meta));

        $sourceIds = $recentMeta
            ->map(fn (array $meta) => $meta['quoted_source_id'] ?? null)
            ->filter()
            ->values()
            ->all();

        $pageIds = $recentMeta
            ->map(fn (array $meta) => $meta['page_id'] ?? null)
            ->filter()
            ->values()
            ->all();

        // New pages are announced once per platform, and a fresh page stays fresh
        // for three weeks, so this has to look back further than the lookback.
        $featuredPageIds = Post::where('business_id', $business->id)
            ->where('platform', $platform)
            ->whereIn('status', self::LIVE_STATUSES)
            ->where('created_at', '>=', now()->subDays(self::FRESH_PAGE_DAYS + 7))
            ->pluck('ai_metadata')
            ->filter(fn ($meta) => is_array($meta) && ($meta['angle'] ?? null) === 'whats_new')
            ->map(fn (array $meta) => $meta['page_id'] ?? null)
            ->filter()
            ->values()
            ->all();

        return [
            'count'             => $count,
            'source_ids'        => $sourceIds,
            'page_ids'          => $pageIds,
            'featured_page_ids' => $featuredPageIds,
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

        // Anything untouched inside the lookback window wins outright. Without
        // this the newest five star review gets quoted every single time the
        // rotation comes back round to it.
        foreach ($pool as $item) {
            if (! in_array($item['id'] ?? null, $usedSourceIds, true)) {
                return $item;
            }
        }

        // Everything has been used recently, which is what happens once a business
        // has fewer reviews than the window holds. Falling back to the first of the
        // pool meant the newest review then ran every time; take the one gone
        // longest without an airing instead. $usedSourceIds is newest first, so a
        // higher index is an older outing.
        $stalest = null;
        $stalestAge = -1;

        foreach ($pool as $item) {
            $age = array_search($item['id'] ?? null, $usedSourceIds, true);

            if ($age === false) {
                continue;
            }

            if ($age > $stalestAge) {
                $stalest = $item;
                $stalestAge = $age;
            }
        }

        return $stalest ?? $pool[0];
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
            array_diff_key($context, array_flip(['reviews', 'website_excerpts', 'website_pages'])),
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

            // A URL costs characters Twitter does not have, so it only goes where
            // a reader can actually follow it.
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

        $page = $angle['page'] ?? null;

        if ($page) {
            $lines[] = '';
            $lines[] = 'WEBSITE PAGE TO WRITE FROM (the business\'s own site, background only, NOT source material for quotes):';
            $lines[] = 'Page: '.($page['title'] ?: $page['url']);
            $lines[] = $page['text'];
            $lines[] = '';
            $lines[] = 'Take the specifics of this post from that page: the services, details, steps and wording it uses. '
                .'Paraphrase in your own words and put no quotation marks around anything from it. '
                .'Do not state anything the page and the business context do not support.';

            if (in_array($angle['angle'], self::LINKED_PAGE_ANGLES, true) && in_array($platform, self::LINK_FRIENDLY_PLATFORMS, true)) {
                $lines[] = 'Finish by pointing readers to the page: '.$page['url'];
                $lines[] = 'Use that URL exactly as written.';
            }
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

        $context['website_excerpts'] = $this->websiteExcerpts($websiteSource, 12);
        $context['website_pages'] = $this->websitePages($websiteSource);
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
     * Each scraped page of the business's site, ready to write a post from.
     *
     * @return array<int, array{id: string, url: string, title: string|null, kind: string, text: string, fresh: bool}>
     */
    private function websitePages(?ContentSource $source): array
    {
        $structured = $source?->structured_data ?? [];
        $firstSeen = $structured['page_first_seen'] ?? [];
        $freshSince = now()->subDays(self::FRESH_PAGE_DAYS)->toDateString();
        $pages = [];

        foreach ($structured['page_text'] ?? [] as $page) {
            $text = $this->trimToSentence((string) ($page['text'] ?? ''), 1500);
            $url = (string) ($page['url'] ?? '');

            // A page with a sentence or two on it gives the model nothing to go on.
            if ($url === '' || mb_strlen($text) < 200) {
                continue;
            }

            $pages[] = [
                'id'    => 'page:'.substr(sha1($url), 0, 12),
                'url'   => $url,
                'title' => $page['title'] ?? null,
                'kind'  => $page['kind'] ?? 'other',
                'text'  => $text,
                // The homepage is never news, however recently we first saw it.
                'fresh' => ($page['kind'] ?? 'other') !== 'home'
                    && isset($firstSeen[$url])
                    && $firstSeen[$url] >= $freshSince,
            ];
        }

        return $pages;
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
        return in_array($platform, ['facebook', 'linkedin', 'google_business_profile']);
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

        if (($rules['max_hashtags'] ?? 0) === 0) {
            return 'Hashtags: none. Do not use a single hashtag anywhere in the post.';
        }

        // Anything outside these rules is stripped in code before publishing
        // (see HashtagGuard), so this is about getting good ones, not the only line of defence.
        return "Hashtags: {$hashtags}, placed at the very end of the post and listed again in the hashtags array. "
            .'Build every hashtag only from words in the business context or source material: the services, '
            .'the industry, the business name, the named location. Never a town, trade or product that is not '
            .'in that material, and never a trending or generic tag unrelated to the post.';
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
