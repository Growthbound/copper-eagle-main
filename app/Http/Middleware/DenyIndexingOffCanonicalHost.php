<?php

namespace App\Http\Middleware;

use App\Support\SearchVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `noindex` as a header, on every response a non-canonical host produces.
 *
 * The meta tag in the page layout only reaches HTML rendered through that
 * layout. A staging host also serves a sitemap listing every URL it has, the
 * images behind them, and any file a crawler reaches directly — none of which
 * can carry a meta tag, and all of which are indexable on their own.
 *
 * Sent as a header rather than left to the markup, this covers all of it, and
 * it is what makes allowing crawlers in safe: they are allowed to fetch
 * precisely so they can read this and drop what they already hold.
 *
 * Global rather than web-only, so it applies before the session starts and
 * regardless of which route group answered.
 */
final readonly class DenyIndexingOffCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! SearchVisibility::indexable()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
