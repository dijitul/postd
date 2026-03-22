<?php

namespace App\Modules\Content\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContentBrief;
use App\Models\ContentSource;
use App\Models\Post;
use App\Modules\Content\Jobs\GeneratePostsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * List all posts for the current business with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['posts' => [], 'meta' => []]);
        }

        $query = Post::where('business_id', $business->id)
            ->with(['brief', 'approvedBy'])
            ->orderBy('scheduled_at', 'desc');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }
        if ($request->filled('from')) {
            $query->where('scheduled_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('scheduled_at', '<=', $request->to);
        }

        $posts = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'posts' => $posts->map(fn ($post) => $this->formatPost($post)),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * The approval inbox — posts awaiting review.
     */
    public function inbox(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['posts' => []]);
        }

        $posts = Post::where('business_id', $business->id)
            ->inbox()
            ->with('brief')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json([
            'posts' => $posts->map(fn ($post) => $this->formatPost($post)),
            'count' => $posts->count(),
        ]);
    }

    /**
     * Get a single post.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $post = Post::with(['brief', 'attempts', 'approvedBy', 'connection'])->findOrFail($id);
        $this->authorise($request, $post);

        return response()->json(['post' => $this->formatPost($post, detailed: true)]);
    }

    /**
     * Approve a post for scheduling.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $this->authorise($request, $post);

        if (! $post->isPending()) {
            return response()->json([
                'message' => "Post cannot be approved — current status is '{$post->status}'.",
                'error' => 'invalid_status',
            ], 422);
        }

        // Accept optional content edit
        if ($request->filled('content')) {
            $post->update(['content_edited' => $request->content]);
        }

        $post->approve($request->user());

        // Schedule it if we have a time
        if ($post->scheduled_at) {
            $post->schedule($post->scheduled_at);
        }

        return response()->json([
            'message' => 'Post approved and scheduled.',
            'post' => $this->formatPost($post->fresh()),
        ]);
    }

    /**
     * Reject a post.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $this->authorise($request, $post);

        if (! in_array($post->status, [Post::STATUS_PENDING, Post::STATUS_APPROVED])) {
            return response()->json([
                'message' => "Post cannot be rejected — current status is '{$post->status}'.",
                'error' => 'invalid_status',
            ], 422);
        }

        $post->reject();

        return response()->json([
            'message' => 'Post rejected.',
            'post' => $this->formatPost($post->fresh()),
        ]);
    }

    /**
     * Retry a failed post.
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $this->authorise($request, $post);

        if (! $post->canRetry()) {
            return response()->json([
                'message' => $post->isFailed()
                    ? 'This post has exceeded the maximum retry attempts.'
                    : "Post cannot be retried — current status is '{$post->status}'.",
                'error' => 'cannot_retry',
            ], 422);
        }

        $post->update([
            'status' => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addMinutes(5),
            'failure_reason' => null,
        ]);

        return response()->json([
            'message' => 'Post queued for retry.',
            'post' => $this->formatPost($post->fresh()),
        ]);
    }

    /**
     * Update a post's content.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $this->authorise($request, $post);

        $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (! in_array($post->status, [Post::STATUS_PENDING, Post::STATUS_APPROVED, Post::STATUS_SCHEDULED])) {
            return response()->json(['message' => 'Post cannot be edited at this stage.'], 422);
        }

        $updates = [];
        if ($request->filled('content')) {
            $updates['content_edited'] = $request->content;
        }
        if ($request->filled('scheduled_at')) {
            $updates['scheduled_at'] = $request->scheduled_at;
        }

        $post->update($updates);

        return response()->json([
            'message' => 'Post updated.',
            'post' => $this->formatPost($post->fresh()),
        ]);
    }

    /**
     * Submit a quick content idea — creates a brief and queues generation.
     */
    public function submitIdea(Request $request): JsonResponse
    {
        $request->validate([
            'idea' => ['required', 'string', 'min:10', 'max:1000'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['in:'.implode(',', Post::PLATFORMS)],
        ]);

        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        // Create a manual content source from the idea
        $source = ContentSource::create([
            'business_id' => $business->id,
            'type' => ContentSource::TYPE_MANUAL,
            'raw_data' => $request->idea,
            'scraped_at' => now(),
        ]);

        $brief = ContentBrief::create([
            'business_id' => $business->id,
            'source_id' => $source->id,
            'theme' => 'Customer-submitted idea',
            'key_messages' => $request->idea,
            'source_type' => ContentSource::TYPE_MANUAL,
        ]);

        GeneratePostsJob::dispatch($business, $brief)->onQueue('generation');

        return response()->json([
            'message' => 'Great idea! Posts are being generated and will appear in your inbox shortly.',
            'brief_id' => $brief->id,
        ], 202);
    }

    /**
     * Manually trigger content generation.
     */
    public function triggerGeneration(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        GeneratePostsJob::dispatch($business)->onQueue('generation');

        return response()->json([
            'message' => 'Content generation triggered. New posts will appear in your inbox shortly.',
        ], 202);
    }

    private function formatPost(Post $post, bool $detailed = false): array
    {
        $data = [
            'id' => $post->id,
            'platform' => $post->platform,
            'content' => $post->getEffectiveContent(),
            'content_original' => $post->content,
            'content_edited' => $post->content_edited,
            'media_urls' => $post->media_urls ?? [],
            'hashtags' => $post->hashtags ?? [],
            'status' => $post->status,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'posted_at' => $post->posted_at?->toIso8601String(),
            'platform_post_url' => $post->platform_post_url,
            'requires_approval' => $post->requires_approval,
            'approved_at' => $post->approved_at?->toIso8601String(),
            'created_at' => $post->created_at->toIso8601String(),
        ];

        if ($post->relationLoaded('brief') && $post->brief) {
            $data['brief'] = [
                'id' => $post->brief->id,
                'theme' => $post->brief->theme,
                'source_type' => $post->brief->source_type,
            ];
        }

        if ($detailed) {
            $data['failure_reason'] = $post->failure_reason;
            $data['retry_count'] = $post->retry_count;
            $data['can_retry'] = $post->canRetry();

            if ($post->relationLoaded('attempts')) {
                $data['attempts'] = $post->attempts->map(fn ($a) => [
                    'attempted_at' => $a->attempted_at->toIso8601String(),
                    'succeeded' => $a->succeeded,
                    'response_code' => $a->response_code,
                    'error_type' => $a->error_type,
                    'duration_ms' => $a->duration_ms,
                ]);
            }
        }

        return $data;
    }

    private function authorise(Request $request, Post $post): void
    {
        $business = $request->user()->business;
        if (! $business || $post->business_id !== $business->id) {
            abort(403, 'You do not have permission to manage this post.');
        }
    }
}
