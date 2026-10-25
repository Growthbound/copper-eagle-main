<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveApiKey
{
    public function __construct(private readonly ApiKeyManager $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->getUser();
        $plaintext = $request->getPassword();
        $key = is_string($email) && is_string($plaintext)
            ? $this->keys->resolve($email, $plaintext)
            : null;

        $request->attributes->set('api_key_resolved', true);

        if ($key !== null) {
            $request->attributes->set('api_key', $key);
            $request->setUserResolver(fn () => $key->user);
        }

        return $next($request);
    }
}
