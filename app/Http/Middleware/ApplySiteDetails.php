<?php

namespace App\Http\Middleware;

use App\Support\SiteDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layers the owner's saved business details over the defaults in
 * config/site.php.
 *
 * This runs per request rather than at boot so a detail saved in the admin is
 * live on the very next page load, without waiting for the process to restart.
 */
class ApplySiteDetails
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (SiteDetails::overrides() as $field => $value) {
            config()->set('site.'.$field, $value);
        }

        return $next($request);
    }
}
