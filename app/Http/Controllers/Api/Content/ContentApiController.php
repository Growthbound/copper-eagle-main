<?php

namespace App\Http\Controllers\Api\Content;

use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\User;
use App\Services\ApiIdempotency;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ContentApiController extends Controller
{
    public function __construct(protected readonly ApiIdempotency $idempotency) {}

    protected function actor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    protected function apiKey(Request $request): ApiKey
    {
        $key = $request->attributes->get('api_key');

        abort_unless($key instanceof ApiKey, 401);

        return $key;
    }

    protected function entry(int|string $id): ContentEntry
    {
        return ContentEntry::query()->findOrFail((int) $id);
    }

    protected function revision(ContentEntry $entry, int|string $id): ContentRevision
    {
        return $entry->revisions()->findOrFail((int) $id);
    }

    protected function lockVersion(Request $request): int|JsonResponse
    {
        $header = $request->header('If-Match');

        if (! is_string($header)
            || preg_match('/^(?:W\/)?"?([0-9]+)"?$/D', trim($header), $matches) !== 1) {
            return $this->error(
                $request,
                'A valid If-Match header containing the current lock version is required.',
                'if_match_required',
                422,
            );
        }

        return (int) $matches[1];
    }

    protected function executeWrite(
        Request $request,
        string $operation,
        Closure $callback,
    ): Response {
        $execute = function () use ($request, $callback): Response {
            try {
                return $callback();
            } catch (ContentValidationFailed $exception) {
                return $this->error(
                    $request,
                    $exception->getMessage(),
                    'content_validation_failed',
                    422,
                    ['errors' => $exception->result->errors()],
                );
            } catch (ContentConflict $exception) {
                return $this->error(
                    $request,
                    $exception->getMessage(),
                    'content_conflict',
                    409,
                    [
                        'expected_lock_version' => $exception->expectedLockVersion,
                        'current_lock_version' => $exception->currentLockVersion,
                    ],
                );
            }
        };

        return $request->attributes->get('idempotency_managed') === true
            ? $execute()
            : $this->idempotency->execute(
                $request,
                $this->apiKey($request),
                $operation,
                $execute,
            );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function error(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'request_id' => $this->requestId($request),
            ...$extra,
        ], $status);
    }

    protected function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id');
    }

    protected function etag(int $lockVersion): string
    {
        return '"'.$lockVersion.'"';
    }
}
