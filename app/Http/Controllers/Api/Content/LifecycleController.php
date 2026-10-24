<?php

namespace App\Http\Controllers\Api\Content;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentSource;
use App\Http\Requests\Api\Content\RollbackContentRequest;
use App\Http\Requests\Api\Content\ScheduleContentRequest;
use App\Http\Resources\ContentEntryResource;
use App\Http\Resources\ContentRevisionResource;
use App\Services\ApiIdempotency;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LifecycleController extends ContentApiController
{
    public function __construct(
        ApiIdempotency $idempotency,
        private readonly ContentLifecycle $lifecycle,
    ) {
        parent::__construct($idempotency);
    }

    public function preview(Request $request, int $entry): Response
    {
        $validated = validator($request->all(), [
            'revision_id' => ['required', 'integer'],
        ])->validate();
        $content = $this->entry($entry);

        return $this->executeWrite(
            $request,
            "content.entry.{$content->id}.preview",
            function () use ($request, $content, $validated): JsonResponse {
                $lockVersion = $this->lockVersion($request);

                if ($lockVersion instanceof JsonResponse) {
                    return $lockVersion;
                }

                $issued = $this->lifecycle->issuePreview(
                    $content,
                    $this->revision($content, $validated['revision_id']),
                    $lockVersion,
                    $this->actor($request),
                    $this->apiKey($request),
                );

                return response()->json([
                    'data' => [
                        'url' => $issued->url(),
                        'expires_at' => $issued->preview->expires_at->toIso8601String(),
                    ],
                    'meta' => ['request_id' => $this->requestId($request)],
                ], 201);
            },
        );
    }

    public function publish(Request $request, int $entry): Response
    {
        $validated = validator($request->all(), [
            'revision_id' => ['required', 'integer'],
        ])->validate();

        return $this->entryMutation(
            $request,
            $entry,
            'publish',
            fn ($content, int $lockVersion) => $this->lifecycle->publish(
                $content,
                $this->revision($content, $validated['revision_id']),
                $lockVersion,
                $this->actor($request),
                $this->apiKey($request),
            ),
        );
    }

    public function schedule(
        ScheduleContentRequest $request,
        int $entry,
    ): Response {
        $validated = $request->validated();

        return $this->entryMutation(
            $request,
            $entry,
            'schedule',
            fn ($content, int $lockVersion) => $this->lifecycle->schedule(
                $content,
                $this->revision($content, $validated['revision_id']),
                CarbonImmutable::parse($validated['publish_at']),
                $lockVersion,
                $this->actor($request),
                $this->apiKey($request),
            ),
        );
    }

    public function cancelSchedule(Request $request, int $entry): Response
    {
        return $this->entryMutation(
            $request,
            $entry,
            'cancel-schedule',
            fn ($content, int $lockVersion) => $this->lifecycle->cancelSchedule(
                $content,
                $lockVersion,
                $this->actor($request),
                $this->apiKey($request),
            ),
        );
    }

    public function rollback(
        RollbackContentRequest $request,
        int $entry,
    ): Response {
        $validated = $request->validated();
        $content = $this->entry($entry);

        return $this->executeWrite(
            $request,
            "content.entry.{$content->id}.rollback",
            function () use ($request, $content, $validated): JsonResponse {
                $lockVersion = $this->lockVersion($request);

                if ($lockVersion instanceof JsonResponse) {
                    return $lockVersion;
                }

                $revision = $this->lifecycle->rollback(
                    $content,
                    $this->revision($content, $validated['revision_id']),
                    $lockVersion,
                    $this->actor($request),
                    ContentSource::Api,
                    $this->apiKey($request),
                );
                $current = $content->fresh();

                return response()
                    ->json([
                        'data' => ContentRevisionResource::make($revision)->resolve(),
                        'meta' => [
                            'lock_version' => $current->lock_version,
                            'request_id' => $this->requestId($request),
                        ],
                    ], 201)
                    ->header('ETag', $this->etag($current->lock_version));
            },
        );
    }

    public function archive(Request $request, int $entry): Response
    {
        return $this->entryMutation(
            $request,
            $entry,
            'archive',
            fn ($content, int $lockVersion) => $this->lifecycle->archive(
                $content,
                $lockVersion,
                $this->actor($request),
                $this->apiKey($request),
            ),
        );
    }

    public function restore(Request $request, int $entry): Response
    {
        return $this->entryMutation(
            $request,
            $entry,
            'restore',
            fn ($content, int $lockVersion) => $this->lifecycle->restore(
                $content,
                $lockVersion,
                $this->actor($request),
                $this->apiKey($request),
            ),
        );
    }

    private function entryMutation(
        Request $request,
        int $entry,
        string $operation,
        callable $callback,
    ): Response {
        $content = $this->entry($entry);

        return $this->executeWrite(
            $request,
            "content.entry.{$content->id}.{$operation}",
            function () use ($request, $content, $callback): JsonResponse {
                $lockVersion = $this->lockVersion($request);

                if ($lockVersion instanceof JsonResponse) {
                    return $lockVersion;
                }

                $current = $callback($content, $lockVersion)->load([
                    'latestRevision',
                    'publishedRevision',
                    'scheduledRevision',
                ]);

                return response()
                    ->json([
                        'data' => ContentEntryResource::make($current)->resolve(),
                        'meta' => ['request_id' => $this->requestId($request)],
                    ])
                    ->header('ETag', $this->etag($current->lock_version));
            },
        );
    }

    private function conflict(
        Request $request,
        int $expected,
        int $current,
    ): JsonResponse {
        return $this->error(
            $request,
            "Content changed after this edit began (expected version {$expected}, current version {$current}).",
            'content_conflict',
            409,
            [
                'expected_lock_version' => $expected,
                'current_lock_version' => $current,
            ],
        );
    }
}
