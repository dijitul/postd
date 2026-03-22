<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasActivePlan()) {
            return response()->json([
                'message' => 'An active subscription is required to access this feature.',
                'error' => 'subscription_required',
                'upgrade_url' => config('app.frontend_url').'/billing/plans',
            ], 402);
        }

        return $next($request);
    }
}
