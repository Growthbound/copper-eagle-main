<?php

namespace App\Http\Middleware;

use App\Content\LegacyUrlRegistry;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The URLs a previous CMS published that no page file will ever recreate.
 *
 * A page migration can be clean — every captured URL keeping its exact path —
 * and the site still lose whole families of URLs around those pages: paginated
 * archives, tag and author archives, feeds, the sitemap the search console has
 * on file, and every uploaded file. None appear in a sitemap of pages, all are
 * crawled and linked from places nobody controls, and all 404 on a fresh
 * application.
 *
 * Which families exist, and where each one goes, is site data in
 * `LegacyUrlRegistry`. This class only applies what that registry declares, so
 * a repository serving another brand — one with a different archive, or one
 * that owns `/feed/` for something else — inherits none of this site's
 * decisions.
 */
final readonly class ApplyLegacyWordPressUrls
{
    public function __construct(private LegacyUrlRegistry $legacy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $target = $this->legacy->targetFor(rawurldecode($request->getPathInfo()));

        if ($target === null) {
            return $next($request);
        }

        /*
         * Attribution is captured on the request that lands, and this one
         * returns before that middleware runs. Dropping the query here would
         * strip the campaign from every lead arriving on an old link: the visit
         * still gets recorded, with no idea where it came from.
         */
        if ($request->getQueryString() !== null) {
            $target .= (str_contains($target, '?') ? '&' : '?').$request->getQueryString();
        }

        return new RedirectResponse($this->absolute($target), 301);
    }

    /**
     * Built rather than passed through `redirect()->to()`, which trims the
     * trailing slash every canonical on this site carries. A target that is
     * already absolute — a media library hosted on another origin — is left
     * exactly as the registry resolved it.
     */
    private function absolute(string $target): string
    {
        return str_starts_with($target, '/')
            ? rtrim(url('/'), '/').$target
            : $target;
    }
}
