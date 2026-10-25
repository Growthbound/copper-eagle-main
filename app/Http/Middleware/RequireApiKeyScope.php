<?php

namespace App\Http\Middleware;

use App\Content\Enums\ApiKeyScope;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireApiKeyScope
{
    public function handle(
        Request $request,
        Closure $next,
        string $scope,
    ): Response {
        $required = ApiKeyScope::tryFrom($scope);
        $key = $request->attributes->get('api_key');

        if (! $required instanceof ApiKeyScope || ! $key instanceof ApiKey || ! $key->hasScope($required)) {
            return new JsonResponse([
                'message' => 'This API key does not have the required scope.',
                'code' => 'missing_scope',
                'required_scope' => $scope,
            ], 403);
        }

        return $next($request);
    }
}
