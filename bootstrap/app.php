<?php

use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Http\Middleware\ApplyLedgerConsolidations;
use App\Http\Middleware\ApplyLegacyWordPressUrls;
use App\Http\Middleware\ApplyManagedRedirects;
use App\Http\Middleware\ApplySiteDetails;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\DenyIndexingOffCanonicalHost;
use App\Http\Middleware\EnsureContentApiRequestId;
use App\Http\Middleware\ProtectWebsiteFormCache;
use App\Http\Middleware\RedirectTwinToCanonicalHost;
use App\Http\Middleware\RequireApiKeyScope;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Http\Middleware\ResolveApiKey;
use App\Http\Middleware\ThrottleApiPeer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_TRAEFIK);

        // First, so the bare/www twin redirects before any other work.
        $middleware->prepend(RedirectTwinToCanonicalHost::class);
        $middleware->append(ApplySiteDetails::class);
        $middleware->append(ProtectWebsiteFormCache::class);
        $middleware->append(DenyIndexingOffCanonicalHost::class);

        // Must run inside the web group rather than globally: global middleware
        // executes before the session is started, and this needs somewhere to
        // remember the visitor's first touch.
        $middleware->web(
            prepend: [
                ApplyLedgerConsolidations::class,
                ApplyLegacyWordPressUrls::class,
                ApplyManagedRedirects::class,
            ],
            append: [CaptureAttribution::class],
        );

        $middleware->alias([
            'api-key.resolve' => ResolveApiKey::class,
            'api-key.auth' => AuthenticateApiKey::class,
            'api-key.scope' => RequireApiKeyScope::class,
            'api-key.idempotency' => RequireIdempotencyKey::class,
            'api-peer-throttle' => ThrottleApiPeer::class,
            'content.request-id' => EnsureContentApiRequestId::class,
        ]);
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            ResolveApiKey::class,
        );
        $middleware->prependToPriorityList(
            ResolveApiKey::class,
            ThrottleApiPeer::class,
        );
        $middleware->prependToPriorityList(
            ThrottleApiPeer::class,
            EnsureContentApiRequestId::class,
        );

        $middleware->redirectGuestsTo(fn (): string => route('admin.login'));
        $middleware->redirectUsersTo(fn (): string => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (
            ContentValidationFailed $exception,
            Request $request,
        ): ?JsonResponse {
            if (! $request->is('api/content/v1*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'content_validation_failed',
                'request_id' => $request->attributes->get('request_id'),
                'errors' => $exception->result->errors(),
            ], 422);
        });

        $exceptions->render(function (
            ContentConflict $exception,
            Request $request,
        ): ?JsonResponse {
            if (! $request->is('api/content/v1*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'content_conflict',
                'request_id' => $request->attributes->get('request_id'),
                'expected_lock_version' => $exception->expectedLockVersion,
                'current_lock_version' => $exception->currentLockVersion,
            ], 409);
        });

        $exceptions->render(function (
            ValidationException $exception,
            Request $request,
        ): ?JsonResponse {
            if (! $request->is('api/content/v1*')) {
                return null;
            }

            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $pointer = '/'.str_replace(
                    ['~', '/', '.'],
                    ['~0', '~1', '/'],
                    $field,
                );
                $errors[$pointer] = $messages;
            }

            return response()->json([
                'message' => 'The request payload failed validation.',
                'code' => 'request_validation_failed',
                'request_id' => $request->attributes->get('request_id'),
                'errors' => $errors,
            ], 422);
        });

        $exceptions->render(function (
            ModelNotFoundException|NotFoundHttpException $exception,
            Request $request,
        ): ?JsonResponse {
            if (! $request->is('api/content/v1*')) {
                return null;
            }

            return response()->json([
                'message' => 'The requested content resource was not found.',
                'code' => 'not_found',
                'request_id' => $request->attributes->get('request_id')
                    ?? (string) Str::uuid(),
            ], 404);
        });
    })->create();
