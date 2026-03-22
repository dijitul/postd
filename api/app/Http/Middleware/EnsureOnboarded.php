<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $business = $user?->business;

        if (! $business?->onboarding_complete) {
            return response()->json([
                'message' => 'Please complete your business setup first.',
                'error' => 'onboarding_required',
                'onboarding_url' => config('app.frontend_url').'/onboarding',
            ], 403);
        }

        return $next($request);
    }
}
