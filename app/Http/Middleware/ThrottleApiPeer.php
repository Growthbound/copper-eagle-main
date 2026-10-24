<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleApiPeer
{
    public function handle(
        Request $request,
        Closure $next,
        string $bucket,
        string $maxAttempts = '120',
    ): Response {
        $limit = max(1, (int) $maxAttempts);
        $key = self::key($bucket, $request);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            return new JsonResponse([
                'message' => 'Too many requests.',
                'code' => 'too_many_requests',
                'request_id' => $request->attributes->get('request_id'),
            ], 429, [
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    public static function key(string $bucket, Request $request): string
    {
        return "{$bucket}:peer:".(string) $request->server(
            'REMOTE_ADDR',
            'unknown',
        );
    }
}
