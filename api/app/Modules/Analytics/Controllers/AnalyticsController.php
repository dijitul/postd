<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Modules\Billing\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Overall analytics overview for the current business.
     */
    public function overview(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['analytics' => []]);
        }

        [$from, $to, $historyDays] = $this->period($request);

        $posts = Post::where('business_id', $business->id)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->get();

        $posted = $posts->where('status', Post::STATUS_POSTED);
        $failed = $posts->where('status', Post::STATUS_FAILED);
        $pending = $posts->whereIn('status', [Post::STATUS_PENDING, Post::STATUS_APPROVED]);
        $scheduled = $posts->where('status', Post::STATUS_SCHEDULED);

        $successRate = $posted->count() + $failed->count() > 0
            ? round(($posted->count() / ($posted->count() + $failed->count())) * 100, 1)
            : 0;

        return response()->json([
            'period' => ['from' => $from, 'to' => $to, 'history_days' => $historyDays],
            'totals' => [
                'total_posts' => $posts->count(),
                'posted' => $posted->count(),
                'pending' => $pending->count(),
                'scheduled' => $scheduled->count(),
                'failed' => $failed->count(),
                'success_rate' => $successRate,
            ],
            'by_platform' => $this->breakdownByPlatform($posts),
            'by_status' => $this->breakdownByStatus($posts),
            'timeline' => $this->buildTimeline($business->id, $from, $to),
            'connected_platforms' => $business->connectedPlatforms(),
        ]);
    }

    /**
     * Platform-specific analytics.
     */
    public function platforms(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['platforms' => []]);
        }

        [$from, $to, $historyDays] = $this->period($request);

        $connections = $business->socialConnections()->with('platformAccounts')->get();

        $platformData = $connections->map(fn ($conn) => [
            'platform' => $conn->platform,
            'is_active' => $conn->is_active,
            'is_expired' => $conn->isExpired(),
            'account_name' => $conn->selectedAccount()->first()?->account_name,
            'posts_this_period' => Post::where('business_id', $business->id)
                ->where('platform', $conn->platform)
                ->where('status', Post::STATUS_POSTED)
                ->whereBetween('posted_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->count(),
            'failed_this_period' => Post::where('business_id', $business->id)
                ->where('platform', $conn->platform)
                ->where('status', Post::STATUS_FAILED)
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->count(),
            'last_post_at' => Post::where('business_id', $business->id)
                ->where('platform', $conn->platform)
                ->where('status', Post::STATUS_POSTED)
                ->latest('posted_at')
                ->value('posted_at'),
        ]);

        return response()->json([
            'period' => ['from' => $from, 'to' => $to, 'history_days' => $historyDays],
            'platforms' => $platformData,
        ]);
    }

    /**
     * Post-level analytics.
     */
    public function posts(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        if (! $business) {
            return response()->json(['posts' => []]);
        }

        $earliest = app(EntitlementService::class)->forUser($request->user())
            ->analyticsEarliest(now())
            ->toDateString();

        $posts = Post::where('business_id', $business->id)
            ->where('status', Post::STATUS_POSTED)
            ->where('posted_at', '>=', $earliest.' 00:00:00')
            ->orderBy('posted_at', 'desc')
            ->limit($request->input('limit', 50))
            ->get();

        return response()->json([
            'posts' => $posts->map(fn ($p) => [
                'id' => $p->id,
                'platform' => $p->platform,
                'posted_at' => $p->posted_at?->toIso8601String(),
                'platform_post_url' => $p->platform_post_url,
                'content_preview' => substr($p->getEffectiveContent(), 0, 100).'...',
                'attempts' => $p->retry_count,
            ]),
        ]);
    }

    /**
     * The requested date range, held to how much history the plan includes
     * (30 days on Local, 12 months above). The older data is still stored, so
     * upgrading brings it straight back.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function period(Request $request): array
    {
        $entitlements = app(EntitlementService::class)->forUser($request->user());
        $earliest = $entitlements->analyticsEarliest(now())->toDateString();

        $from = (string) $request->input('from', now()->subDays(30)->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        // Y-m-d strings compare correctly as strings.
        return [max($from, $earliest), $to, $entitlements->analyticsDays];
    }

    private function breakdownByPlatform($posts): array
    {
        return $posts->groupBy('platform')->map(fn ($group, $platform) => [
            'platform' => $platform,
            'total' => $group->count(),
            'posted' => $group->where('status', Post::STATUS_POSTED)->count(),
            'failed' => $group->where('status', Post::STATUS_FAILED)->count(),
        ])->values()->toArray();
    }

    private function breakdownByStatus($posts): array
    {
        return $posts->groupBy('status')->map(fn ($group, $status) => [
            'status' => $status,
            'count' => $group->count(),
        ])->values()->toArray();
    }

    private function buildTimeline(string $businessId, string $from, string $to): array
    {
        return Post::where('business_id', $businessId)
            ->where('status', Post::STATUS_POSTED)
            ->whereBetween('posted_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw("DATE(posted_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => $row->count])
            ->toArray();
    }
}
