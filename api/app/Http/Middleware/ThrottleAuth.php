<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuth
{
    public function __construct(
        private readonly RateLimiter $limiter
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'auth:'.$request->ip();

        if ($this->limiter->tooManyAttempts($key, 5)) {
            $seconds = $this->limiter->availableIn($key);

            return response()->json([
                'message' => "Too many attempts. Please wait {$seconds} seconds.",
                'error' => 'too_many_attempts',
                'retry_after' => $seconds,
            ], 429);
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
