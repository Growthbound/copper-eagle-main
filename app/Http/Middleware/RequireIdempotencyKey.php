<?php

namespace App\Http\Middleware;

use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Models\ApiKey;
use App\Services\ApiIdempotency;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class RequireIdempotencyKey
{
    public function __construct(
        private readonly ApiIdempotency $idempotency,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key)
            || preg_match('/^[A-Za-z0-9._:-]{8,200}$/D', $key) !== 1) {
            return new JsonResponse([
                'message' => 'A valid Idempotency-Key header is required for this write.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        $request->attributes->set('idempotency_key', $key);
        $request->attributes->set('idempotency_managed', true);
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            throw new LogicException('Idempotent API writes require an authenticated API key.');
        }

        $route = $request->route();
        $operation = is_object($route) && method_exists($route, 'getName')
            ? $route->getName()
            : null;
        $operation = is_string($operation) && $operation !== ''
            ? $operation
            : strtolower($request->method()).':/'.$request->path();
        $callback = function () use ($request, $next): Response {
            try {
                $response = $next($request);
            } catch (
                ContentConflict
                |ContentValidationFailed
                |ModelNotFoundException
                |NotFoundHttpException
                |ValidationException $exception
            ) {
                $handler = app(ExceptionHandler::class);
                $handler->report($exception);

                return $handler->render($request, $exception);
            }

            $exception = property_exists($response, 'exception')
                ? $response->exception
                : null;

            if ($exception instanceof Throwable
                && ! $this->isReplayableFailure($exception)) {
                $request->attributes->set(
                    'idempotency_exception_response',
                    $response,
                );

                throw $exception;
            }

            return $response;
        };

        return $operation === 'api.media.import'
            ? $this->idempotency->executeExternal(
                $request,
                $apiKey,
                $operation,
                $callback,
            )
            : $this->idempotency->execute(
                $request,
                $apiKey,
                $operation,
                $callback,
            );
    }

    private function isReplayableFailure(Throwable $exception): bool
    {
        return $exception instanceof ContentConflict
            || $exception instanceof ContentValidationFailed
            || $exception instanceof ModelNotFoundException
            || $exception instanceof NotFoundHttpException
            || $exception instanceof ValidationException;
    }
}
