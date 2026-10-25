<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyManager $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->attributes->get('api_key');

        if ($request->attributes->get('api_key_resolved')) {
            if ($key !== null) {
                $this->keys->recordUsage($key);
            }
        } else {
            $email = $request->getUser();
            $plaintext = $request->getPassword();
            $key = is_string($email) && is_string($plaintext)
                ? $this->keys->authenticate($email, $plaintext)
                : null;
        }

        if ($key === null) {
            return $this->unauthorized();
        }

        $request->attributes->set('api_key', $key);
        $request->setUserResolver(fn () => $key->user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()
            ->json(['message' => 'Invalid API credentials.'], 401)
            ->header('WWW-Authenticate', 'Basic realm="Content API"');
    }
}
