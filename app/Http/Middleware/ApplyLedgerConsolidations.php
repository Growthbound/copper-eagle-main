<?php

namespace App\Http\Middleware;

use App\Content\UrlDispositionRegistry;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the folds the URL/intent ledger has already decided on.
 *
 * Runs ahead of `ApplyManagedRedirects` because the two answer to different
 * authorities and must not compete: this one reads a versioned file that
 * reviewers can see in a diff, the other reads owner-managed database rows.
 * Their source paths cannot overlap — every path here sits under a
 * `ReservedPublicPath` prefix, which the managed redirect validator refuses.
 *
 * 301 rather than 302: these duplicates never existed on the legacy site, so
 * there is no equity to preserve, but there is a live duplicate to retire, and
 * a temporary redirect would leave it competing for its pillar's query
 * indefinitely.
 */
final readonly class ApplyLedgerConsolidations
{
    public function __construct(private UrlDispositionRegistry $dispositions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $target = $this->dispositions->targetFor($request->getPathInfo());

        if ($target === null) {
            return $next($request);
        }

        // Campaign parameters survive the hop, so a link that folded still
        // attributes the visit it brought in.
        if ($request->getQueryString() !== null) {
            $target .= '?'.$request->getQueryString();
        }

        /*
         * Built rather than passed through `redirect()->to()`, which trims the
         * trailing slash. Every pillar on this site canonicalizes itself with
         * one, so trimming would land the hop on a URL whose own canonical
         * points somewhere else and make a one-hop fold look like a chain.
         */
        return new RedirectResponse(rtrim(url('/'), '/').$target, 301);
    }
}
