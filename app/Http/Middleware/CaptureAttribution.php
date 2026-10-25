<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use App\Support\WebsiteForms;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records where a visitor came from, once per session.
 *
 * Runs on public page views only: the admin is not a traffic source, and
 * capturing there would overwrite a real visit if the owner happened to be
 * browsing in the same browser.
 */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldCapture($request)) {
            return $next($request);
        }

        // Website attribution upgrades independently after discarding malformed
        // values; native attribution keeps its existing first-touch semantics.
        WebsiteForms::rememberCampaign($request->query());

        $stored = Attribution::stored();
        $incoming = Attribution::fromRequest($request);

        // Keep the first touch, unless the session started with no campaign at
        // all and one has since appeared.
        if ($stored === [] || (! Attribution::hasSource($stored) && Attribution::hasSource($incoming))) {
            Attribution::remember($incoming);
        }

        return $next($request);
    }

    private function shouldCapture(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->is('admin', 'admin/*')
            && ! $request->is('api/*')
            && ! $request->is('livewire/*')
            && ! $request->expectsJson()
            && $request->hasSession();
    }
}
