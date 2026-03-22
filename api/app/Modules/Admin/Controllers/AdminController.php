<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Post;
use App\Models\SystemHealthLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Global stats dashboard for Dijitul team.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 30); // days

        // MRR calculation from Stripe subscriptions
        $activeSubscriptions = \DB::table('subscriptions')
            ->where('stripe_status', 'active')
            ->count();

        // User metrics
        $totalUsers = User::count();
        $trialUsers = User::onTrial()->count();
        $activeSubscribed = User::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count();
        $newUsersThisPeriod = User::where('created_at', '>=', now()->subDays($period))->count();

        // Churn this month
        $churnedThisMonth = User::whereHas('subscriptions', fn ($q) =>
            $q->where('stripe_status', 'canceled')
              ->where('updated_at', '>=', now()->startOfMonth())
        )->count();

        // Content metrics
        $postsGenerated = Post::where('created_at', '>=', now()->subDays($period))->count();
        $postsPosted = Post::where('status', Post::STATUS_POSTED)
            ->where('posted_at', '>=', now()->subDays($period))
            ->count();
        $postsFailed = Post::where('status', Post::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays($period))
            ->count();

        // Platform connection health
        $totalConnections = \DB::table('social_connections')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();
        $expiredConnections = \DB::table('social_connections')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $connectionHealthPct = $totalConnections > 0
            ? round((($totalConnections - $expiredConnections) / $totalConnections) * 100, 1)
            : 100;

        // AI costs this month
        $aiCostThisMonth = \DB::table('ai_cost_logs')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_usd');

        $aiCostPerBusiness = $activeSubscribed > 0
            ? round($aiCostThisMonth / $activeSubscribed, 4)
            : 0;

        return response()->json([
            'period_days' => $period,
            'users' => [
                'total' => $totalUsers,
                'on_trial' => $trialUsers,
                'subscribed' => $activeSubscribed,
                'new_this_period' => $newUsersThisPeriod,
                'churned_this_month' => $churnedThisMonth,
            ],
            'content' => [
                'posts_generated' => $postsGenerated,
                'posts_posted' => $postsPosted,
                'posts_failed' => $postsFailed,
                'success_rate' => $postsPosted + $postsFailed > 0
                    ? round(($postsPosted / ($postsPosted + $postsFailed)) * 100, 1)
                    : 0,
            ],
            'connections' => [
                'total' => $totalConnections,
                'expired' => $expiredConnections,
                'health_pct' => $connectionHealthPct,
            ],
            'ai_costs' => [
                'total_usd_this_month' => round($aiCostThisMonth, 2),
                'per_business_usd' => $aiCostPerBusiness,
            ],
        ]);
    }

    /**
     * List all businesses (paginated).
     */
    public function businesses(Request $request): JsonResponse
    {
        $query = Business::with(['user', 'activeSocialConnections'])
            ->withCount(['posts', 'pendingPosts'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where(fn ($q) =>
                $q->where('name', 'ilike', '%'.$request->search.'%')
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', '%'.$request->search.'%'))
            );
        }

        if ($request->filled('onboarded')) {
            $query->where('onboarding_complete', $request->boolean('onboarded'));
        }

        $businesses = $query->paginate(20);

        return response()->json([
            'businesses' => $businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'industry' => $b->industry,
                'owner_email' => $b->user->email,
                'onboarding_complete' => $b->onboarding_complete,
                'connected_platforms' => $b->connectedPlatforms(),
                'posts_count' => $b->posts_count,
                'pending_posts_count' => $b->pending_posts_count,
                'created_at' => $b->created_at->toIso8601String(),
                'last_generated_at' => $b->last_generated_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'total' => $businesses->total(),
            ],
        ]);
    }

    /**
     * Get detailed info about a single business.
     */
    public function business(Request $request, string $id): JsonResponse
    {
        $business = Business::with([
            'user',
            'settings',
            'socialConnections.platformAccounts',
            'contentSources' => fn ($q) => $q->latest()->limit(5),
        ])->withCount(['posts', 'pendingPosts', 'scheduledPosts'])->findOrFail($id);

        $recentPosts = Post::where('business_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'platform', 'status', 'scheduled_at', 'posted_at', 'created_at']);

        // AI costs for this business
        $aiCostThisMonth = \DB::table('ai_cost_logs')
            ->where('business_id', $id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_usd');

        return response()->json([
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => $business->industry,
                'website_url' => $business->website_url,
                'google_reviews_url' => $business->google_reviews_url,
                'tone' => $business->tone,
                'onboarding_complete' => $business->onboarding_complete,
                'created_at' => $business->created_at->toIso8601String(),
                'last_scraped_at' => $business->last_scraped_at?->toIso8601String(),
                'last_generated_at' => $business->last_generated_at?->toIso8601String(),
            ],
            'owner' => [
                'id' => $business->user->id,
                'name' => $business->user->name,
                'email' => $business->user->email,
                'plan' => $business->user->activePlanName(),
                'subscribed' => $business->user->subscribed(),
                'on_trial' => $business->user->isOnValidTrial(),
                'trial_ends_at' => $business->user->trial_ends_at?->toIso8601String(),
            ],
            'connections' => $business->socialConnections->map(fn ($c) => [
                'platform' => $c->platform,
                'is_active' => $c->is_active,
                'is_expired' => $c->isExpired(),
                'last_used_at' => $c->last_used_at?->toIso8601String(),
                'last_error_message' => $c->last_error_message,
            ]),
            'posts' => [
                'total' => $business->posts_count,
                'pending' => $business->pending_posts_count,
                'scheduled' => $business->scheduled_posts_count,
                'recent' => $recentPosts,
            ],
            'ai_cost_this_month_usd' => round($aiCostThisMonth, 2),
            'settings' => $business->settings,
        ]);
    }

    /**
     * System health overview.
     */
    public function health(Request $request): JsonResponse
    {
        $recentLogs = SystemHealthLog::with(['business', 'connection'])
            ->orderBy('checked_at', 'desc')
            ->limit(50)
            ->get();

        $statusSummary = $recentLogs->groupBy('status')->map->count();

        // Connections expiring in next 7 days
        $expiringConnections = \DB::table('social_connections')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        // Recent failed posts
        $recentFailures = Post::where('status', Post::STATUS_FAILED)
            ->where('updated_at', '>=', now()->subHours(24))
            ->count();

        return response()->json([
            'status_summary' => $statusSummary,
            'expiring_connections_7d' => $expiringConnections,
            'failed_posts_24h' => $recentFailures,
            'recent_logs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'check_type' => $log->check_type,
                'status' => $log->status,
                'message' => $log->message,
                'business_name' => $log->business?->name,
                'platform' => $log->connection?->platform,
                'checked_at' => $log->checked_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Businesses at risk of churning (trial expiring, no posts, failing connections).
     */
    public function atRisk(Request $request): JsonResponse
    {
        // Trial users expiring in next 3 days
        $trialExpiringSoon = User::onTrial()
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->with('businesses')
            ->get()
            ->map(fn ($u) => [
                'type' => 'trial_expiring',
                'user_id' => $u->id,
                'user_email' => $u->email,
                'business_name' => $u->businesses->first()?->name,
                'trial_ends_at' => $u->trial_ends_at->toIso8601String(),
                'days_remaining' => now()->diffInDays($u->trial_ends_at),
            ]);

        // Active businesses with no posts in the last 7 days
        $staleBusinesses = Business::where('onboarding_complete', true)
            ->whereDoesntHave('posts', fn ($q) =>
                $q->where('created_at', '>=', now()->subDays(7))
            )
            ->with('user')
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'type' => 'no_recent_posts',
                'business_id' => $b->id,
                'business_name' => $b->name,
                'user_email' => $b->user->email,
                'last_generated_at' => $b->last_generated_at?->toIso8601String(),
            ]);

        // Businesses with all social connections failing
        $brokenConnections = Business::where('onboarding_complete', true)
            ->whereHas('socialConnections', fn ($q) =>
                $q->where('is_active', false)->whereNull('deleted_at')
            )
            ->whereDoesntHave('socialConnections', fn ($q) => $q->where('is_active', true))
            ->with('user')
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'type' => 'all_connections_broken',
                'business_id' => $b->id,
                'business_name' => $b->name,
                'user_email' => $b->user->email,
            ]);

        return response()->json([
            'at_risk' => [
                'trial_expiring_soon' => $trialExpiringSoon,
                'stale_no_posts' => $staleBusinesses,
                'broken_connections' => $brokenConnections,
            ],
            'totals' => [
                'trial_expiring' => $trialExpiringSoon->count(),
                'stale' => $staleBusinesses->count(),
                'broken' => $brokenConnections->count(),
            ],
        ]);
    }

    /**
     * AI cost breakdown by business.
     */
    public function aiCosts(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $costs = \DB::table('ai_cost_logs')
            ->join('businesses', 'ai_cost_logs.business_id', '=', 'businesses.id')
            ->whereBetween('ai_cost_logs.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('
                businesses.id as business_id,
                businesses.name as business_name,
                SUM(cost_usd) as total_cost_usd,
                SUM(total_tokens) as total_tokens,
                COUNT(*) as operations,
                MAX(ai_cost_logs.created_at) as last_operation
            ')
            ->groupBy('businesses.id', 'businesses.name')
            ->orderByDesc('total_cost_usd')
            ->get();

        $totalCost = $costs->sum('total_cost_usd');

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'total_cost_usd' => round($totalCost, 4),
            'total_cost_gbp_approx' => round($totalCost * 0.79, 4), // approximate conversion
            'by_business' => $costs->map(fn ($row) => [
                'business_id' => $row->business_id,
                'business_name' => $row->business_name,
                'total_cost_usd' => round($row->total_cost_usd, 4),
                'total_tokens' => $row->total_tokens,
                'operations' => $row->operations,
                'last_operation' => $row->last_operation,
            ]),
        ]);
    }

    /**
     * Impersonate a business owner (admin only).
     * Returns a temporary token scoped to that user.
     */
    public function impersonate(Request $request, string $id): JsonResponse
    {
        $business = Business::findOrFail($id);
        $user = $business->user;

        // Create a short-lived impersonation token
        $token = $user->createToken(
            'admin-impersonation-'.auth()->id(),
            ['impersonation'],
            now()->addHours(2)
        )->plainTextToken;

        return response()->json([
            'message' => "Impersonating {$user->email}. Token expires in 2 hours.",
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
