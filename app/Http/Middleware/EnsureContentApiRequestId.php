<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureContentApiRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Request-ID');
        $requestId = is_string($incoming)
            && preg_match('/^[A-Za-z0-9._:-]{8,100}$/D', $incoming) === 1
                ? $incoming
                : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-ID', $requestId);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $rendered = $request->attributes->get(
                'idempotency_exception_response',
            );

            if ($rendered instanceof Response) {
                $response = $rendered;
            } else {
                $handler = app(ExceptionHandler::class);
                $handler->report($exception);
                $response = $handler->render($request, $exception);
            }
        }

        $response->headers->set('X-Request-ID', $requestId);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $payload = $response->getData(true);

            if (is_array($payload)) {
                $payload['code'] ??= $this->defaultCode($response->getStatusCode());
                $payload['request_id'] ??= $requestId;
                $response->setData($payload);
            }
        }

        return $response;
    }

    private function defaultCode(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            default => 'request_failed',
        };
    }
}
