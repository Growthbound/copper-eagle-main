<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectWebsiteFormCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->attributes->get('website-form-rendered') === true) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }
}
