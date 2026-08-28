<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Post;
use App\Models\SystemHealthLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdminController extends Controller
{
    /**
     * Approximate USD to GBP rate, used only for cost display.
     */
    private const USD_TO_GBP = 0.79;

    // ── Overview ──────────────────────────────────────────────────────────

    /**
     * Everything the Dijitul team needs on one screen: revenue, customers,
     * content throughput, connection health, AI spend and a daily series.
     */
    public function overview(Request $request): JsonResponse
    {
        $period = (int) $request->input('period', 30);
        $period = max(1, min($period, 365));
        $since = now()->subDays($period);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'period_days' => $period,
            'revenue' => $this->revenueSnapshot(),
            'customers' => $this->customerSnapshot($since),
            'content' => $this->contentSnapshot($since),
            'connections' => $this->connectionSnapshot(),
            'ai' => $this->aiSnapshot($since),
            'series' => $this->dailySeries($period),
        ]);
    }

    /**
     * Kept as an alias so older clients calling /admin/stats keep working.
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->overview($request);
    }

    private function revenueSnapshot(): array
    {
        $plans = config('cashier.plans', []);

        // stripe_price_id => plan key, so subscriptions can be priced locally
        // rather than round-tripping to Stripe on every dashboard load.
        $priceToPlan = [];
        foreach ($plans as $key => $plan) {
            if (! empty($plan['stripe_price_id'])) {
                $priceToPlan[$plan['stripe_price_id']] = $key;
            }
        }

        $activeRows = DB::table('subscriptions')
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->selectRaw('stripe_price, COUNT(*) as count')
            ->groupBy('stripe_price')
            ->get();

        $byPlan = [];
        $mrrPence = 0;
        $unknownCount = 0;

        foreach ($activeRows as $row) {
            $planKey = $priceToPlan[$row->stripe_price] ?? null;

            if ($planKey === null) {
                $unknownCount += (int) $row->count;

                continue;
            }

            $price = (int) ($plans[$planKey]['price'] ?? 0);
            $planMrr = $price * (int) $row->count;
            $mrrPence += $planMrr;

            $byPlan[$planKey] = [
                'plan' => $planKey,
                'name' => $plans[$planKey]['name'] ?? ucfirst($planKey),
                'price_pence' => $price,
                'customers' => (int) $row->count,
                'mrr_pence' => $planMrr,
            ];
        }

        // Value we are choosing to give away, so comps are a visible decision
        // rather than an invisible hole in the revenue line.
        $compedRows = User::query()->comped()
            ->selectRaw('comped_plan, COUNT(*) as count')
            ->groupBy('comped_plan')
            ->get();

        $compedPence = 0;
        $compedCount = 0;
        foreach ($compedRows as $row) {
            $price = (int) ($plans[$row->comped_plan]['price'] ?? 0);
            $compedPence += $price * (int) $row->count;
            $compedCount += (int) $row->count;
        }

        $activeCount = array_sum(array_column($byPlan, 'customers'));

        // Cancellations that took effect this month
        $churnedPence = 0;
        $churnedRows = DB::table('subscriptions')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', now()->startOfMonth())
            ->where('ends_at', '<=', now())
            ->selectRaw('stripe_price, COUNT(*) as count')
            ->groupBy('stripe_price')
            ->get();
        foreach ($churnedRows as $row) {
            $planKey = $priceToPlan[$row->stripe_price] ?? null;
            $churnedPence += (int) ($plans[$planKey]['price'] ?? 0) * (int) $row->count;
        }

        return [
            'mrr_pence' => $mrrPence,
            'mrr' => round($mrrPence / 100, 2),
            'arr' => round(($mrrPence * 12) / 100, 2),
            'arpa' => $activeCount > 0 ? round(($mrrPence / $activeCount) / 100, 2) : 0,
            'active_subscriptions' => $activeCount,
            'unpriced_subscriptions' => $unknownCount,
            'comped_customers' => $compedCount,
            'comped_mrr_forgone' => round($compedPence / 100, 2),
            'churned_mrr_this_month' => round($churnedPence / 100, 2),
            'by_plan' => array_values(array_map(fn ($p) => $p + [
                'mrr' => round($p['mrr_pence'] / 100, 2),
                'price' => round($p['price_pence'] / 100, 2),
            ], $byPlan)),
        ];
    }

    private function customerSnapshot(Carbon $since): array
    {
        $total = User::count();
        $trialing = User::query()->trialing()->count();
        $comped = User::query()->comped()->count();

        $subscribed = User::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count();

        $expired = User::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))
            ->whereNull('comped_plan')
            ->count();

        $churnedThisMonth = DB::table('subscriptions')
            ->where('stripe_status', 'canceled')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->distinct('user_id')
            ->count('user_id');

        $activeLast7 = User::where('last_seen_at', '>=', now()->subDays(7))->count();

        return [
            'total' => $total,
            'subscribed' => $subscribed,
            'trialing' => $trialing,
            'comped' => $comped,
            'expired' => $expired,
            'new_this_period' => User::where('created_at', '>=', $since)->count(),
            'churned_this_month' => $churnedThisMonth,
            'active_last_7d' => $activeLast7,
            'businesses' => Business::count(),
            'onboarded' => Business::where('onboarding_complete', true)->count(),
            'not_onboarded' => Business::where('onboarding_complete', false)->count(),
            'trial_to_paid_pct' => ($subscribed + $expired) > 0
                ? round(($subscribed / ($subscribed + $expired)) * 100, 1)
                : 0,
        ];
    }

    private function contentSnapshot(Carbon $since): array
    {
        $counts = Post::where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $posted = Post::where('status', Post::STATUS_POSTED)
            ->where('posted_at', '>=', $since)
            ->count();
        $failed = (int) ($counts[Post::STATUS_FAILED] ?? 0);

        $byPlatform = Post::where('created_at', '>=', $since)
            ->selectRaw("platform,
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'posted') as posted,
                COUNT(*) FILTER (WHERE status = 'failed') as failed")
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'platform' => $row->platform,
                'total' => (int) $row->total,
                'posted' => (int) $row->posted,
                'failed' => (int) $row->failed,
                'success_rate' => ((int) $row->posted + (int) $row->failed) > 0
                    ? round(((int) $row->posted / ((int) $row->posted + (int) $row->failed)) * 100, 1)
                    : null,
            ]);

        return [
            'generated' => (int) $counts->sum(),
            'posted' => $posted,
            'failed' => $failed,
            'awaiting_approval' => Post::where('status', Post::STATUS_PENDING)->count(),
            'scheduled' => Post::where('status', Post::STATUS_SCHEDULED)->count(),
            'posted_24h' => Post::where('status', Post::STATUS_POSTED)
                ->where('posted_at', '>=', now()->subDay())->count(),
            'failed_24h' => Post::where('status', Post::STATUS_FAILED)
                ->where('updated_at', '>=', now()->subDay())->count(),
            'success_rate' => ($posted + $failed) > 0
                ? round(($posted / ($posted + $failed)) * 100, 1)
                : 0,
            'by_platform' => $byPlatform,
        ];
    }

    private function connectionSnapshot(): array
    {
        $rows = DB::table('social_connections')
            ->whereNull('deleted_at')
            ->selectRaw("platform,
                COUNT(*) FILTER (WHERE is_active) as active,
                COUNT(*) FILTER (WHERE is_active AND expires_at IS NOT NULL AND expires_at < now()) as expired,
                COUNT(*) FILTER (WHERE is_active AND expires_at IS NOT NULL AND expires_at > now() AND expires_at <= now() + interval '7 days') as expiring,
                COUNT(*) FILTER (WHERE NOT is_active) as broken")
            ->groupBy('platform')
            ->get();

        $total = (int) $rows->sum('active');
        $expired = (int) $rows->sum('expired');

        return [
            'total' => $total,
            'expired' => $expired,
            'expiring_7d' => (int) $rows->sum('expiring'),
            'broken' => (int) $rows->sum('broken'),
            'health_pct' => $total > 0 ? round((($total - $expired) / $total) * 100, 1) : 100,
            'by_platform' => $rows->map(fn ($r) => [
                'platform' => $r->platform,
                'active' => (int) $r->active,
                'expired' => (int) $r->expired,
                'expiring_7d' => (int) $r->expiring,
                'broken' => (int) $r->broken,
            ])->values(),
        ];
    }

    private function aiSnapshot(Carbon $since): array
    {
        $month = DB::table('ai_cost_logs')
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost, COALESCE(SUM(total_tokens), 0) as tokens, COUNT(*) as ops')
            ->first();

        $period = DB::table('ai_cost_logs')
            ->where('created_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost, COALESCE(SUM(total_tokens), 0) as tokens, COUNT(*) as ops')
            ->first();

        // Everything ever spent, so the running total is visible and not just
        // the current calendar month.
        $allTime = DB::table('ai_cost_logs')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost,
                COALESCE(SUM(total_tokens), 0) as tokens,
                COALESCE(SUM(prompt_tokens), 0) as prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as completion_tokens,
                COUNT(*) as ops')
            ->first();

        $payingCustomers = max(1, User::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count());
        $monthCost = (float) ($month->cost ?? 0);
        $allTimeCost = (float) ($allTime->cost ?? 0);

        return [
            'cost_usd_this_month' => round($monthCost, 2),
            'cost_gbp_this_month' => round($monthCost * self::USD_TO_GBP, 2),
            'tokens_this_month' => (int) ($month->tokens ?? 0),
            'operations_this_month' => (int) ($month->ops ?? 0),

            'cost_usd_period' => round((float) ($period->cost ?? 0), 2),
            'tokens_period' => (int) ($period->tokens ?? 0),
            'operations_period' => (int) ($period->ops ?? 0),

            'cost_usd_all_time' => round($allTimeCost, 2),
            'cost_gbp_all_time' => round($allTimeCost * self::USD_TO_GBP, 2),
            'tokens_all_time' => (int) ($allTime->tokens ?? 0),
            'prompt_tokens_all_time' => (int) ($allTime->prompt_tokens ?? 0),
            'completion_tokens_all_time' => (int) ($allTime->completion_tokens ?? 0),
            'operations_all_time' => (int) ($allTime->ops ?? 0),

            'per_customer_usd' => round($monthCost / $payingCustomers, 2),
            'cost_per_1k_tokens_usd' => ($allTime->tokens ?? 0) > 0
                ? round(($allTimeCost / (int) $allTime->tokens) * 1000, 5)
                : 0,
        ];
    }

    /**
     * Daily signups, posts published and failures for the sparkline charts.
     * Zero-filled so the series always has one point per day.
     */
    private function dailySeries(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $signups = User::where('created_at', '>=', $since)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, COUNT(*) as count")
            ->groupBy('day')->pluck('count', 'day');

        $posted = Post::where('posted_at', '>=', $since)
            ->where('status', Post::STATUS_POSTED)
            ->selectRaw("to_char(posted_at, 'YYYY-MM-DD') as day, COUNT(*) as count")
            ->groupBy('day')->pluck('count', 'day');

        $failed = Post::where('updated_at', '>=', $since)
            ->where('status', Post::STATUS_FAILED)
            ->selectRaw("to_char(updated_at, 'YYYY-MM-DD') as day, COUNT(*) as count")
            ->groupBy('day')->pluck('count', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $since->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'signups' => (int) ($signups[$day] ?? 0),
                'posted' => (int) ($posted[$day] ?? 0),
                'failed' => (int) ($failed[$day] ?? 0),
            ];
        }

        return $series;
    }

    // ── Customers ─────────────────────────────────────────────────────────

    /**
     * Paginated customer list, one row per account, with plan and activity.
     */
    public function customers(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        $query = User::query()
            ->with(['subscriptions', 'businesses' => fn ($q) => $q->withCount(['posts', 'pendingPosts'])]);

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn ($q) => $q
                ->where('email', 'ilike', $term)
                ->orWhere('name', 'ilike', $term)
                ->orWhereHas('businesses', fn ($b) => $b->where('name', 'ilike', $term)));
        }

        match ($request->input('status')) {
            'subscribed' => $query->whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active')),
            'trialing' => $query->trialing()->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active')),
            'comped' => $query->comped(),
            'expired' => $query->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<', now())
                ->whereNull('comped_plan')
                ->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active')),
            'not_onboarded' => $query->whereDoesntHave('businesses', fn ($b) => $b->where('onboarding_complete', true)),
            default => null,
        };

        $sort = in_array($request->input('sort'), ['created_at', 'last_seen_at', 'name', 'email'], true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $users = $query->orderBy($sort, $direction)->paginate($perPage);

        $businessIds = $users->getCollection()
            ->flatMap(fn ($u) => $u->businesses->pluck('id'))
            ->all();

        $costs = $this->aiCostsByBusiness($businessIds);
        $platforms = $this->platformsByBusiness($businessIds);
        $lastPosts = $this->lastPostedAtByBusiness($businessIds);

        return response()->json([
            'customers' => $users->getCollection()->map(
                fn (User $user) => $this->customerRow($user, $costs, $platforms, $lastPosts)
            )->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    private function customerRow(User $user, array $costs, array $platforms, array $lastPosts): array
    {
        $business = $user->businesses->first();
        $plans = config('cashier.plans', []);
        $plan = $user->activePlanName();
        $status = $user->billingStatus();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'status' => $status,
            'plan' => $plan,
            'plan_label' => $plans[$plan]['name'] ?? ucfirst($plan),
            'mrr' => $status === 'subscribed' ? round((int) ($plans[$plan]['price'] ?? 0) / 100, 2) : 0,
            'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
            'comped_until' => $user->comped_until?->toIso8601String(),
            'comp_note' => $user->comp_note,
            'created_at' => $user->created_at->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => $business->industry,
                'onboarding_complete' => $business->onboarding_complete,
                'posts_count' => $business->posts_count,
                'pending_posts_count' => $business->pending_posts_count,
                'last_generated_at' => $business->last_generated_at?->toIso8601String(),
                'last_posted_at' => $business ? ($lastPosts[$business->id] ?? null) : null,
                'platforms' => $business ? ($platforms[$business->id] ?? []) : [],
                'ai_cost_month_usd' => $business ? round($costs[$business->id] ?? 0, 2) : 0,
            ] : null,
        ];
    }

    /**
     * Full detail for one customer: account, business, connections, posts,
     * spend and recent activity.
     */
    public function customer(Request $request, string $id): JsonResponse
    {
        $user = User::with([
            'subscriptions',
            'businesses.settings',
            'businesses.socialConnections.platformAccounts',
        ])->findOrFail($id);

        $business = $user->businesses->first();
        $plans = config('cashier.plans', []);
        $plan = $user->activePlanName();

        $recentPosts = $business
            ? Post::where('business_id', $business->id)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(['id', 'platform', 'status', 'content', 'scheduled_at', 'posted_at', 'failure_reason', 'created_at'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'platform' => $p->platform,
                    'status' => $p->status,
                    'excerpt' => \Illuminate\Support\Str::limit((string) $p->content, 120),
                    'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                    'posted_at' => $p->posted_at?->toIso8601String(),
                    'failure_reason' => $p->failure_reason,
                    'created_at' => $p->created_at->toIso8601String(),
                ])
            : collect();

        $postCounts = $business
            ? Post::where('business_id', $business->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->pluck('count', 'status')
            : collect();

        $aiCost = $business
            ? (float) DB::table('ai_cost_logs')
                ->where('business_id', $business->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('cost_usd')
            : 0;

        $aiCostAllTime = $business
            ? (float) DB::table('ai_cost_logs')->where('business_id', $business->id)->sum('cost_usd')
            : 0;

        return response()->json([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'status' => $user->billingStatus(),
                'plan' => $plan,
                'plan_label' => $plans[$plan]['name'] ?? ucfirst($plan),
                'mrr' => $user->billingStatus() === 'subscribed'
                    ? round((int) ($plans[$plan]['price'] ?? 0) / 100, 2)
                    : 0,
                'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
                'comped_plan' => $user->comped_plan,
                'comped_at' => $user->comped_at?->toIso8601String(),
                'comped_until' => $user->comped_until?->toIso8601String(),
                'comp_note' => $user->comp_note,
                'comped_by' => $user->comped_by
                    ? User::where('id', $user->comped_by)->value('name')
                    : null,
                'stripe_customer' => $user->stripe_id !== null,
            ],
            'subscription' => $user->subscriptions
                ->sortByDesc('created_at')
                ->map(fn ($s) => [
                    'stripe_status' => $s->stripe_status,
                    'plan' => User::planKeyForPriceId($s->stripe_price),
                    'created_at' => $s->created_at?->toIso8601String(),
                    'ends_at' => $s->ends_at?->toIso8601String(),
                ])->values(),
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => $business->industry,
                'website_url' => $business->website_url,
                'google_reviews_url' => $business->google_reviews_url,
                'city' => $business->city,
                'tone' => $business->tone,
                'is_active' => $business->is_active,
                'onboarding_complete' => $business->onboarding_complete,
                'last_scraped_at' => $business->last_scraped_at?->toIso8601String(),
                'last_generated_at' => $business->last_generated_at?->toIso8601String(),
                'created_at' => $business->created_at->toIso8601String(),
            ] : null,
            'connections' => $business
                ? $business->socialConnections->map(fn ($c) => [
                    'id' => $c->id,
                    'platform' => $c->platform,
                    'is_active' => $c->is_active,
                    'is_expired' => $c->isExpired(),
                    'expires_at' => $c->expires_at?->toIso8601String(),
                    'last_used_at' => $c->last_used_at?->toIso8601String(),
                    'last_error_message' => $c->last_error_message,
                    'accounts' => $c->platformAccounts->map(fn ($a) => [
                        'name' => $a->account_name,
                        'is_selected' => $a->is_selected,
                    ])->values(),
                ])->values()
                : collect(),
            'posts' => [
                'counts' => $postCounts,
                'total' => (int) $postCounts->sum(),
                'recent' => $recentPosts,
            ],
            'ai_cost' => [
                'this_month_usd' => round($aiCost, 2),
                'all_time_usd' => round($aiCostAllTime, 2),
            ],
        ]);
    }

    // ── Customer actions ──────────────────────────────────────────────────

    /**
     * Give a customer their plan for free, optionally until a date.
     * Comps take precedence over Stripe, so an existing subscription is left
     * untouched: cancelling it is a separate, deliberate action in Stripe.
     */
    public function comp(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(config('cashier.plans', [])))],
            'until' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);

        $user->forceFill([
            'comped_plan' => $validated['plan'],
            'comped_at' => now(),
            'comped_until' => $validated['until'] ?? null,
            'comped_by' => $request->user()->id,
            'comp_note' => $validated['note'] ?? null,
        ])->save();

        $this->logAdminAction($request, 'customer.comped', $user, [
            'plan' => $validated['plan'],
            'until' => $validated['until'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => "{$user->email} is now on a free {$validated['plan']} plan.",
            'status' => $user->billingStatus(),
            'plan' => $user->activePlanName(),
            'comped_until' => $user->comped_until?->toIso8601String(),
        ]);
    }

    /**
     * Remove a comp. The customer falls back to their subscription or trial.
     */
    public function uncomp(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $user->forceFill([
            'comped_plan' => null,
            'comped_at' => null,
            'comped_until' => null,
            'comped_by' => null,
            'comp_note' => null,
        ])->save();

        $this->logAdminAction($request, 'customer.comp_removed', $user);

        return response()->json([
            'message' => "Free access removed for {$user->email}.",
            'status' => $user->billingStatus(),
            'plan' => $user->activePlanName(),
        ]);
    }

    /**
     * Push a customer's trial end date out by a number of days.
     */
    public function extendTrial(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $user = User::findOrFail($id);

        // Extend from today when the trial has already lapsed, otherwise the
        // extension would be swallowed by the time already gone.
        $base = $user->trial_ends_at && $user->trial_ends_at->isFuture()
            ? $user->trial_ends_at
            : now();

        $user->forceFill(['trial_ends_at' => $base->copy()->addDays($validated['days'])])->save();

        $this->logAdminAction($request, 'customer.trial_extended', $user, ['days' => $validated['days']]);

        return response()->json([
            'message' => "Trial extended by {$validated['days']} days.",
            'trial_ends_at' => $user->trial_ends_at->toIso8601String(),
            'status' => $user->billingStatus(),
        ]);
    }

    /**
     * Impersonate a customer. Returns a short-lived token scoped to them.
     */
    public function impersonate(Request $request, string $id): JsonResponse
    {
        // Accepts a user ID, or a business ID for older callers.
        $user = User::find($id) ?? Business::findOrFail($id)->user;

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Admin accounts cannot be impersonated.',
                'error' => 'forbidden',
            ], 403);
        }

        $token = $user->createToken(
            'admin-impersonation-'.$request->user()->id,
            ['impersonation'],
            now()->addHours(2)
        )->plainTextToken;

        $this->logAdminAction($request, 'customer.impersonated', $user);

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

    // ── Activity ──────────────────────────────────────────────────────────

    /**
     * A merged, reverse-chronological feed of what has actually happened:
     * signups, subscriptions, posts, connections and failures.
     */
    public function activity(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 60), 200);
        $since = now()->subDays((int) $request->input('days', 7));
        $events = collect();

        User::where('created_at', '>=', $since)
            ->orderByDesc('created_at')->limit($limit)
            ->get(['id', 'name', 'email', 'created_at'])
            ->each(fn ($u) => $events->push([
                'type' => 'signup',
                'severity' => 'info',
                'at' => $u->created_at->toIso8601String(),
                'title' => 'New signup',
                'detail' => $u->email,
                'customer_id' => $u->id,
            ]));

        Business::where('onboarding_completed_at', '>=', $since)
            ->with('user:id,email')
            ->orderByDesc('onboarding_completed_at')->limit($limit)
            ->get()
            ->each(fn ($b) => $events->push([
                'type' => 'onboarded',
                'severity' => 'success',
                'at' => $b->onboarding_completed_at->toIso8601String(),
                'title' => 'Onboarding complete',
                'detail' => $b->name,
                'customer_id' => $b->user_id,
            ]));

        DB::table('subscriptions')
            ->join('users', 'subscriptions.user_id', '=', 'users.id')
            ->where('subscriptions.created_at', '>=', $since)
            ->orderByDesc('subscriptions.created_at')->limit($limit)
            ->get(['users.id as user_id', 'users.email', 'subscriptions.stripe_price', 'subscriptions.stripe_status', 'subscriptions.created_at'])
            ->each(fn ($s) => $events->push([
                'type' => 'subscription',
                'severity' => $s->stripe_status === 'active' ? 'success' : 'warning',
                'at' => Carbon::parse($s->created_at)->toIso8601String(),
                'title' => 'Subscription '.$s->stripe_status,
                'detail' => $s->email.' — '.(User::planKeyForPriceId($s->stripe_price) ?? 'unknown plan'),
                'customer_id' => $s->user_id,
            ]));

        Post::where('status', Post::STATUS_POSTED)
            ->where('posted_at', '>=', $since)
            ->with('business:id,name,user_id')
            ->orderByDesc('posted_at')->limit($limit)
            ->get(['id', 'business_id', 'platform', 'posted_at'])
            ->each(fn ($p) => $events->push([
                'type' => 'post_published',
                'severity' => 'success',
                'at' => $p->posted_at->toIso8601String(),
                'title' => 'Post published to '.$this->platformLabel($p->platform),
                'detail' => $p->business?->name ?? 'Unknown business',
                'customer_id' => $p->business?->user_id,
            ]));

        Post::where('status', Post::STATUS_FAILED)
            ->where('updated_at', '>=', $since)
            ->with('business:id,name,user_id')
            ->orderByDesc('updated_at')->limit($limit)
            ->get(['id', 'business_id', 'platform', 'failure_reason', 'updated_at'])
            ->each(fn ($p) => $events->push([
                'type' => 'post_failed',
                'severity' => 'error',
                'at' => $p->updated_at->toIso8601String(),
                'title' => 'Post failed on '.$this->platformLabel($p->platform),
                'detail' => trim(($p->business?->name ?? 'Unknown').' — '.\Illuminate\Support\Str::limit((string) $p->failure_reason, 90)),
                'customer_id' => $p->business?->user_id,
            ]));

        DB::table('social_connections')
            ->join('businesses', 'social_connections.business_id', '=', 'businesses.id')
            ->whereNull('social_connections.deleted_at')
            ->where('social_connections.created_at', '>=', $since)
            ->orderByDesc('social_connections.created_at')->limit($limit)
            ->get(['businesses.name', 'businesses.user_id', 'social_connections.platform', 'social_connections.created_at'])
            ->each(fn ($c) => $events->push([
                'type' => 'platform_connected',
                'severity' => 'info',
                'at' => Carbon::parse($c->created_at)->toIso8601String(),
                'title' => $this->platformLabel($c->platform).' connected',
                'detail' => $c->name,
                'customer_id' => $c->user_id,
            ]));

        return response()->json([
            'events' => $events->sortByDesc('at')->take($limit)->values(),
            'since' => $since->toIso8601String(),
        ]);
    }

    // ── Health ────────────────────────────────────────────────────────────

    /**
     * Live infrastructure probes rather than a hardcoded green list.
     */
    public function health(Request $request): JsonResponse
    {
        $services = [
            $this->probe('Database (PostgreSQL)', fn () => DB::select('select 1') !== null),
            $this->probe('Redis', function () {
                Redis::connection()->ping();

                return true;
            }),
            $this->probe('Cache', function () {
                Cache::put('admin_health_probe', 1, 10);

                return Cache::get('admin_health_probe') === 1;
            }),
            $this->horizonProbe(),
            $this->schedulerProbe(),
        ];

        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $recentFailedJobs = DB::table('failed_jobs')
            ->orderByRaw('id DESC')
            ->limit(10)
            ->get(['id', 'queue', 'exception', 'failed_at'])
            ->map(fn ($j) => [
                'id' => $j->id,
                'queue' => $j->queue,
                'error' => \Illuminate\Support\Str::limit(strtok((string) $j->exception, "\n"), 160),
                'failed_at' => Carbon::parse($j->failed_at)->toIso8601String(),
            ]);

        $recentLogs = SystemHealthLog::with(['business:id,name', 'connection:id,platform'])
            ->orderByDesc('checked_at')
            ->limit(30)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'check_type' => $log->check_type,
                'status' => $log->status,
                'message' => $log->message,
                'business_name' => $log->business?->name,
                'platform' => $log->connection?->platform,
                'checked_at' => $log->checked_at->toIso8601String(),
            ]);

        return response()->json([
            'checked_at' => now()->toIso8601String(),
            'services' => $services,
            'all_healthy' => collect($services)->every(fn ($s) => $s['healthy']),
            'queues' => $this->queueDepths(),
            'failed_jobs_24h' => $failedJobs,
            'recent_failed_jobs' => $recentFailedJobs,
            'failed_posts_24h' => Post::where('status', Post::STATUS_FAILED)
                ->where('updated_at', '>=', now()->subDay())->count(),
            'expiring_connections_7d' => DB::table('social_connections')
                ->whereNull('deleted_at')->where('is_active', true)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(7)])
                ->count(),
            'recent_logs' => $recentLogs,
        ]);
    }

    private function probe(string $name, callable $check): array
    {
        $start = microtime(true);

        try {
            $healthy = (bool) $check();
            $message = $healthy ? 'Operational' : 'Check returned false';
        } catch (\Throwable $e) {
            $healthy = false;
            $message = $e->getMessage();
        }

        return [
            'name' => $name,
            'healthy' => $healthy,
            'message' => $message,
            'latency_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    private function horizonProbe(): array
    {
        return $this->probe('Queue workers (Horizon)', function () {
            $repository = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class);
            $masters = collect($repository->all());

            if ($masters->isEmpty()) {
                throw new \RuntimeException('No Horizon supervisors running');
            }

            if ($masters->contains(fn ($m) => ($m->status ?? null) === 'paused')) {
                throw new \RuntimeException('Horizon is paused');
            }

            return true;
        });
    }

    /**
     * The scheduler writes a heartbeat every minute; if it has gone quiet the
     * cron entry has stopped and nothing is being dispatched.
     */
    private function schedulerProbe(): array
    {
        return $this->probe('Scheduler (cron)', function () {
            $last = Cache::get('scheduler_heartbeat');

            if (! $last) {
                throw new \RuntimeException('No heartbeat recorded yet');
            }

            $age = Carbon::parse($last)->diffInMinutes(now());

            if ($age > 5) {
                throw new \RuntimeException("Last run {$age} minutes ago");
            }

            return true;
        });
    }

    private function queueDepths(): array
    {
        $queues = ['posting', 'generation', 'scraping', 'default'];
        $depths = [];

        foreach ($queues as $queue) {
            try {
                $depths[] = [
                    'queue' => $queue,
                    'pending' => Redis::connection('default')->llen("queues:{$queue}"),
                ];
            } catch (\Throwable $e) {
                $depths[] = ['queue' => $queue, 'pending' => null];
            }
        }

        return $depths;
    }

    // ── At risk ───────────────────────────────────────────────────────────

    /**
     * Accounts worth a phone call: trials about to lapse, silent businesses
     * and broken platform connections. Returned as one flat, ranked list.
     */
    public function atRisk(Request $request): JsonResponse
    {
        $risks = collect();

        User::query()->trialing()
            ->where('trial_ends_at', '<=', now()->addDays(3))
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))
            ->whereNull('comped_plan')
            ->with('businesses:id,user_id,name')
            ->limit(25)->get()
            ->each(fn ($u) => $risks->push([
                'customer_id' => $u->id,
                'name' => $u->businesses->first()?->name ?? $u->name,
                'email' => $u->email,
                'risk' => 'high',
                'reason' => 'trial_expiring',
                'detail' => 'Trial ends '.$u->trial_ends_at->diffForHumans(),
                'at' => $u->trial_ends_at->toIso8601String(),
            ]));

        Business::where('onboarding_complete', true)
            ->whereDoesntHave('posts', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->with('user:id,email')
            ->limit(25)->get()
            ->each(fn ($b) => $risks->push([
                'customer_id' => $b->user_id,
                'name' => $b->name,
                'email' => $b->user?->email,
                'risk' => 'medium',
                'reason' => 'no_recent_posts',
                'detail' => $b->last_generated_at
                    ? 'Last generated '.$b->last_generated_at->diffForHumans()
                    : 'Never generated any posts',
                'at' => $b->last_generated_at?->toIso8601String(),
            ]));

        Business::where('onboarding_complete', true)
            ->whereHas('socialConnections', fn ($q) => $q->where('is_active', false))
            ->whereDoesntHave('socialConnections', fn ($q) => $q->where('is_active', true))
            ->with('user:id,email')
            ->limit(25)->get()
            ->each(fn ($b) => $risks->push([
                'customer_id' => $b->user_id,
                'name' => $b->name,
                'email' => $b->user?->email,
                'risk' => 'high',
                'reason' => 'all_connections_broken',
                'detail' => 'Every platform connection is disconnected',
                'at' => null,
            ]));

        Business::where('onboarding_complete', false)
            ->where('created_at', '<=', now()->subDays(3))
            ->where('created_at', '>=', now()->subDays(30))
            ->with('user:id,email')
            ->limit(25)->get()
            ->each(fn ($b) => $risks->push([
                'customer_id' => $b->user_id,
                'name' => $b->name,
                'email' => $b->user?->email,
                'risk' => 'medium',
                'reason' => 'stalled_onboarding',
                'detail' => 'Signed up '.$b->created_at->diffForHumans().', never finished setup',
                'at' => $b->created_at->toIso8601String(),
            ]));

        $sorted = $risks->sortBy(fn ($r) => $r['risk'] === 'high' ? 0 : 1)->values();

        return response()->json([
            'at_risk' => $sorted,
            'totals' => [
                'high' => $sorted->where('risk', 'high')->count(),
                'medium' => $sorted->where('risk', 'medium')->count(),
                'total' => $sorted->count(),
            ],
        ]);
    }

    // ── AI costs ──────────────────────────────────────────────────────────

    public function aiCosts(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $costs = DB::table('ai_cost_logs')
            ->join('businesses', 'ai_cost_logs.business_id', '=', 'businesses.id')
            ->whereBetween('ai_cost_logs.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('
                businesses.id as business_id,
                businesses.user_id as customer_id,
                businesses.name as business_name,
                SUM(cost_usd) as total_cost_usd,
                SUM(total_tokens) as total_tokens,
                COUNT(*) as operations,
                MAX(ai_cost_logs.created_at) as last_operation
            ')
            ->groupBy('businesses.id', 'businesses.user_id', 'businesses.name')
            ->orderByDesc('total_cost_usd')
            ->get();

        $byOperation = DB::table('ai_cost_logs')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('operation, model,
                SUM(cost_usd) as cost,
                SUM(total_tokens) as tokens,
                SUM(prompt_tokens) as prompt_tokens,
                SUM(completion_tokens) as completion_tokens,
                COUNT(*) as operations')
            ->groupBy('operation', 'model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'operation' => $r->operation,
                'model' => $r->model,
                'cost_usd' => round((float) $r->cost, 4),
                'tokens' => (int) $r->tokens,
                'prompt_tokens' => (int) $r->prompt_tokens,
                'completion_tokens' => (int) $r->completion_tokens,
                'operations' => (int) $r->operations,
            ]);

        $totalCost = (float) $costs->sum('total_cost_usd');
        $totalTokens = (int) $costs->sum('total_tokens');
        $totalOperations = (int) $costs->sum('operations');

        // Daily spend across the range, for the trend chart.
        $daily = DB::table('ai_cost_logs')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as date,
                SUM(cost_usd) as cost,
                SUM(total_tokens) as tokens")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->date,
                'cost_usd' => round((float) $r->cost, 4),
                'tokens' => (int) $r->tokens,
            ]);

        $allTime = DB::table('ai_cost_logs')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost,
                COALESCE(SUM(total_tokens), 0) as tokens,
                COALESCE(SUM(prompt_tokens), 0) as prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as completion_tokens,
                COUNT(*) as ops,
                MIN(created_at) as first_operation')
            ->first();

        $allTimeCost = (float) ($allTime->cost ?? 0);

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'total_cost_usd' => round($totalCost, 2),
            'total_cost_gbp' => round($totalCost * self::USD_TO_GBP, 2),
            'total_tokens' => $totalTokens,
            'total_operations' => $totalOperations,
            'all_time' => [
                'cost_usd' => round($allTimeCost, 2),
                'cost_gbp' => round($allTimeCost * self::USD_TO_GBP, 2),
                'tokens' => (int) ($allTime->tokens ?? 0),
                'prompt_tokens' => (int) ($allTime->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($allTime->completion_tokens ?? 0),
                'operations' => (int) ($allTime->ops ?? 0),
                'cost_per_1k_tokens_usd' => ($allTime->tokens ?? 0) > 0
                    ? round(($allTimeCost / (int) $allTime->tokens) * 1000, 5)
                    : 0,
                'since' => $allTime->first_operation
                    ? Carbon::parse($allTime->first_operation)->toIso8601String()
                    : null,
            ],
            'daily' => $daily,
            'by_operation' => $byOperation,
            'by_business' => $costs->map(fn ($row) => [
                'business_id' => $row->business_id,
                'customer_id' => $row->customer_id,
                'business_name' => $row->business_name,
                'total_cost_usd' => round((float) $row->total_cost_usd, 4),
                'total_tokens' => (int) $row->total_tokens,
                'operations' => (int) $row->operations,
                'last_operation' => $row->last_operation
                    ? Carbon::parse($row->last_operation)->toIso8601String()
                    : null,
            ]),
        ]);
    }

    // ── Legacy business endpoints ─────────────────────────────────────────

    /**
     * Business list, kept for anything still pointed at /admin/businesses.
     */
    public function businesses(Request $request): JsonResponse
    {
        $query = Business::with(['user', 'activeSocialConnections'])
            ->withCount(['posts', 'pendingPosts'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $term)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $term)));
        }

        if ($request->filled('onboarded')) {
            $query->where('onboarding_complete', $request->boolean('onboarded'));
        }

        $businesses = $query->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'businesses' => $businesses->getCollection()->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'industry' => $b->industry,
                'customer_id' => $b->user_id,
                'owner_email' => $b->user?->email,
                'onboarding_complete' => $b->onboarding_complete,
                'connected_platforms' => $b->connectedPlatforms(),
                'posts_count' => $b->posts_count,
                'pending_posts_count' => $b->pending_posts_count,
                'created_at' => $b->created_at->toIso8601String(),
                'last_generated_at' => $b->last_generated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'total' => $businesses->total(),
            ],
        ]);
    }

    /**
     * Single business detail, resolved through the customer payload so both
     * views stay in step.
     */
    public function business(Request $request, string $id): JsonResponse
    {
        $business = Business::findOrFail($id);

        return $this->customer($request, $business->user_id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array<string, float> business_id => cost this month
     */
    private function aiCostsByBusiness(array $businessIds): array
    {
        if (empty($businessIds)) {
            return [];
        }

        return DB::table('ai_cost_logs')
            ->whereIn('business_id', $businessIds)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('business_id, SUM(cost_usd) as cost')
            ->groupBy('business_id')
            ->pluck('cost', 'business_id')
            ->map(fn ($c) => (float) $c)
            ->all();
    }

    /**
     * @return array<string, array<int, string>> business_id => platforms
     */
    private function platformsByBusiness(array $businessIds): array
    {
        if (empty($businessIds)) {
            return [];
        }

        return DB::table('social_connections')
            ->whereIn('business_id', $businessIds)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get(['business_id', 'platform'])
            ->groupBy('business_id')
            ->map(fn (Collection $rows) => $rows->pluck('platform')->unique()->values()->all())
            ->all();
    }

    /**
     * @return array<string, string> business_id => last posted_at
     */
    private function lastPostedAtByBusiness(array $businessIds): array
    {
        if (empty($businessIds)) {
            return [];
        }

        return Post::whereIn('business_id', $businessIds)
            ->where('status', Post::STATUS_POSTED)
            ->selectRaw('business_id, MAX(posted_at) as last_posted_at')
            ->groupBy('business_id')
            ->pluck('last_posted_at', 'business_id')
            ->map(fn ($t) => $t ? Carbon::parse($t)->toIso8601String() : null)
            ->all();
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'google_business_profile' => 'Google Business Profile',
            'twitter' => 'X',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            default => ucfirst($platform),
        };
    }

    /**
     * Record an admin action against the customer so comps and impersonations
     * are traceable after the fact.
     */
    private function logAdminAction(Request $request, string $type, User $target, array $payload = []): void
    {
        try {
            DB::table('notification_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $target->id,
                'type' => $type,
                'channel' => 'admin',
                'status' => 'sent',
                'payload' => json_encode($payload + [
                    'admin_id' => $request->user()->id,
                    'admin_email' => $request->user()->email,
                ]),
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // An audit line failing must never block the action itself.
            \Illuminate\Support\Facades\Log::warning('AdminController: audit log failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
