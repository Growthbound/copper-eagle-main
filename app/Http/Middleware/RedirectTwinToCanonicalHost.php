<?php

namespace App\Http\Middleware;

use App\Support\SearchVisibility;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the bare domain to www, or www to the bare domain, whichever is not
 * the canonical host.
 *
 * Both names usually point at the site at launch. Served side by side they are
 * two copies of every page, and links and rankings split between them. Only
 * the canonical host's www or bare twin redirects: any other host (a staging
 * host, say) stays reachable and is left to the noindex rules.
 *
 * GET and HEAD only, so a form posted to the twin is not turned into a GET
 * and lost.
 */
final readonly class RedirectTwinToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = SearchVisibility::canonicalHost();
        $host = strtolower($request->getHost());

        if ($canonical !== null
            && $host !== $canonical
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && self::twin($canonical) === $host) {
            return new RedirectResponse($request->getScheme().'://'.$canonical.$request->getRequestUri(), 301);
        }

        return $next($request);
    }

    private static function twin(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
    }
}
