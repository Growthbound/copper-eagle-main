<?php

namespace App\Services;

use App\Models\ApiIdempotencyRecord;
use App\Models\ApiKey;
use App\Services\Exceptions\IdempotencyResponseTooLarge;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiIdempotency
{
    private const MAX_RESPONSE_BYTES = 1_048_576;

    public function execute(
        Request $request,
        ApiKey $key,
        string $operation,
        Closure $callback,
    ): Response {
        $plaintext = $request->attributes->get('idempotency_key')
            ?? $request->header('Idempotency-Key');

        if (! is_string($plaintext)) {
            return $this->error(
                'A valid Idempotency-Key header is required for this write.',
                'idempotency_key_required',
                422,
            );
        }

        $keyHash = hash('sha256', $plaintext);
        $requestHash = hash('sha256', json_encode(
            $this->canonicalRequest($request),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        try {
            return DB::transaction(function () use (
                $request,
                $key,
                $operation,
                $keyHash,
                $requestHash,
                $callback,
            ): Response {
                $record = ApiIdempotencyRecord::query()
                    ->where('api_key_id', $key->id)
                    ->where('idempotency_key_hash', $keyHash)
                    ->lockForUpdate()
                    ->first();

                if ($record !== null && $record->expires_at->isPast()) {
                    $record->delete();
                    $record = null;
                }

                if ($record !== null) {
                    return $this->responseForRecord($record, $operation, $requestHash);
                }

                $record = ApiIdempotencyRecord::create([
                    'api_key_id' => $key->id,
                    'operation' => $operation,
                    'idempotency_key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'response_status' => 102,
                    'response_body' => ['code' => 'idempotency_in_progress'],
                    'response_headers' => [],
                    'expires_at' => now()->addDay(),
                ]);

                $response = $callback();
                $this->applyRequestId($request, $response);
                $this->persistResponse($record, $response, $operation);

                $response->headers->set('Idempotency-Replayed', 'false');

                return $response;
            }, attempts: 3);
        } catch (IdempotencyResponseTooLarge) {
            return $this->error(
                'The write response exceeded the idempotency storage limit.',
                'idempotency_response_too_large',
                500,
            );
        } catch (QueryException $exception) {
            if (! $this->isUniqueClaimViolation($exception)) {
                throw $exception;
            }

            $record = ApiIdempotencyRecord::query()
                ->where('api_key_id', $key->id)
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if ($record === null) {
                throw $exception;
            }

            return $this->responseForRecord($record, $operation, $requestHash);
        }
    }

    /**
     * Claim and finalize a replay ledger in short transactions while the
     * callback performs non-transactional side effects such as filesystem
     * writes.
     */
    public function executeExternal(
        Request $request,
        ApiKey $key,
        string $operation,
        Closure $callback,
    ): Response {
        $plaintext = $request->attributes->get('idempotency_key')
            ?? $request->header('Idempotency-Key');

        if (! is_string($plaintext)) {
            return $this->error(
                'A valid Idempotency-Key header is required for this write.',
                'idempotency_key_required',
                422,
            );
        }

        $keyHash = hash('sha256', $plaintext);
        $requestHash = hash('sha256', json_encode(
            $this->canonicalRequest($request),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        try {
            $claim = DB::transaction(function () use (
                $key,
                $operation,
                $keyHash,
                $requestHash,
            ): ApiIdempotencyRecord|Response {
                $record = ApiIdempotencyRecord::query()
                    ->where('api_key_id', $key->id)
                    ->where('idempotency_key_hash', $keyHash)
                    ->lockForUpdate()
                    ->first();

                if ($record !== null && $record->expires_at->isPast()) {
                    $record->delete();
                    $record = null;
                }

                if ($record !== null) {
                    return $this->responseForRecord($record, $operation, $requestHash);
                }

                return ApiIdempotencyRecord::create([
                    'api_key_id' => $key->id,
                    'operation' => $operation,
                    'idempotency_key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'response_status' => 102,
                    'response_body' => ['code' => 'idempotency_in_progress'],
                    'response_headers' => [],
                    'expires_at' => now()->addDay(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueClaimViolation($exception)) {
                throw $exception;
            }

            $record = ApiIdempotencyRecord::query()
                ->where('api_key_id', $key->id)
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if ($record === null) {
                throw $exception;
            }

            return $this->responseForRecord($record, $operation, $requestHash);
        }

        if ($claim instanceof Response) {
            return $claim;
        }

        try {
            $response = $callback();
        } catch (Throwable $exception) {
            $this->releaseExternalClaim($claim);

            throw $exception;
        }

        $this->applyRequestId($request, $response);

        try {
            $this->finalizeExternalClaim($claim, $response, $operation);
        } catch (IdempotencyResponseTooLarge) {
            $replayResponse = $this->compactedReplayResponse($response);
            $this->applyRequestId($request, $replayResponse);
            $this->finalizeExternalClaim($claim, $replayResponse, $operation);
        }

        $response->headers->set('Idempotency-Replayed', 'false');

        return $response;
    }

    private function compactedReplayResponse(Response $response): JsonResponse
    {
        $replay = response()->json([
            'message' => 'The original write completed, but its response exceeded the replay storage limit.',
            'code' => 'idempotency_replay_compacted',
        ], $response->getStatusCode());

        foreach ($this->replayHeaders($response) as $name => $value) {
            $replay->headers->set($name, $value);
        }

        return $replay;
    }

    private function responseForRecord(
        ApiIdempotencyRecord $record,
        string $operation,
        string $requestHash,
    ): Response {
        if ($record->operation !== $operation
            || ! hash_equals($record->request_hash, $requestHash)) {
            return $this->error(
                'This idempotency key was already used with a different request.',
                'idempotency_conflict',
                409,
            );
        }

        if ($record->response_status === 102) {
            return $this->error(
                'A write using this idempotency key is already in progress.',
                'idempotency_in_progress',
                409,
            );
        }

        $response = response()
            ->json($this->responseBodyForRecord($record), $record->response_status);

        foreach ($record->response_headers ?? [] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response->header('Idempotency-Replayed', 'true');
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBodyForRecord(ApiIdempotencyRecord $record): array
    {
        if (! is_string($record->response_body_ciphertext)
            || $record->response_body_ciphertext === '') {
            return $record->response_body;
        }

        $decoded = json_decode(
            Crypt::decryptString($record->response_body_ciphertext),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    private function isSensitiveOperation(string $operation): bool
    {
        return str_ends_with($operation, '.preview');
    }

    private function persistResponse(
        ApiIdempotencyRecord $record,
        Response $response,
        string $operation,
    ): void {
        $body = $this->responseBody($response);

        if (strlen(json_encode($body, JSON_THROW_ON_ERROR)) > self::MAX_RESPONSE_BYTES) {
            throw new IdempotencyResponseTooLarge;
        }

        $ciphertext = $this->isSensitiveOperation($operation)
            ? Crypt::encryptString(json_encode(
                $body,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ))
            : null;

        $record->forceFill([
            'response_status' => $response->getStatusCode(),
            'response_body' => $ciphertext === null
                ? $body
                : ['code' => 'encrypted_idempotency_replay'],
            'response_body_ciphertext' => $ciphertext,
            'response_headers' => $this->replayHeaders($response),
            'expires_at' => now()->addDay(),
        ])->save();
    }

    private function applyRequestId(Request $request, Response $response): void
    {
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && $requestId !== '') {
            $response->headers->set('X-Request-ID', $requestId);
        }
    }

    private function releaseExternalClaim(ApiIdempotencyRecord $claim): void
    {
        try {
            ApiIdempotencyRecord::query()
                ->whereKey($claim->id)
                ->where('response_status', 102)
                ->delete();
        } catch (Throwable $exception) {
            Log::error('Failed to release an incomplete external idempotency claim.', [
                'record_id' => $claim->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function finalizeExternalClaim(
        ApiIdempotencyRecord $claim,
        Response $response,
        string $operation,
    ): void {
        DB::transaction(function () use (
            $claim,
            $operation,
            $response,
        ): void {
            $record = ApiIdempotencyRecord::query()
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->persistResponse($record, $response, $operation);
        }, attempts: 3);
    }

    private function isUniqueClaimViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'api_idempotency')
            && (
                str_contains($message, 'api_idempotency_key_unique')
                || (
                    str_contains($message, 'api_key_id')
                    && str_contains($message, 'idempotency_key_hash')
                )
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalRequest(Request $request): array
    {
        return [
            'method' => strtoupper($request->method()),
            'path' => '/'.ltrim($request->path(), '/'),
            'query' => $this->sort($request->query()),
            'body' => $this->sort($request->all()),
            'if_match' => $request->header('If-Match'),
        ];
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $child): mixed => $this->sort($child), $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(Response $response): array
    {
        $decoded = json_decode($response->getContent() ?: '{}', true);

        return is_array($decoded)
            ? $decoded
            : ['data' => $decoded];
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $status);
    }

    /**
     * @return array<string, string>
     */
    private function replayHeaders(Response $response): array
    {
        $headers = [];

        foreach (['ETag', 'X-Request-ID'] as $name) {
            $value = $response->headers->get($name);

            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
