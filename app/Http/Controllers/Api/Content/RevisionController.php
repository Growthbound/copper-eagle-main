<?php

namespace App\Http\Controllers\Api\Content;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentSource;
use App\Http\Requests\Api\Content\StoreRevisionRequest;
use App\Http\Resources\ContentRevisionResource;
use App\Services\ApiIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RevisionController extends ContentApiController
{
    public function __construct(
        ApiIdempotency $idempotency,
        private readonly ContentLifecycle $lifecycle,
    ) {
        parent::__construct($idempotency);
    }

    public function index(Request $request, int $entry): JsonResponse
    {
        $content = $this->entry($entry);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $paginator = $content->revisions()
            ->orderByDesc('revision_number')
            ->paginate($perPage);

        return response()->json([
            'data' => ContentRevisionResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function show(Request $request, int $entry, int $revision): JsonResponse
    {
        $content = $this->entry($entry);
        $record = $this->revision($content, $revision);

        return response()->json([
            'data' => ContentRevisionResource::make($record)->resolve(),
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    public function store(
        StoreRevisionRequest $request,
        int $entry,
    ): Response {
        $content = $this->entry($entry);

        return $this->executeWrite(
            $request,
            "content.entry.{$content->id}.revision",
            function () use ($request, $content): JsonResponse {
                $lockVersion = $this->lockVersion($request);

                if ($lockVersion instanceof JsonResponse) {
                    return $lockVersion;
                }

                $validated = $request->validated();
                $revision = $this->lifecycle->saveRevision(
                    $content,
                    $validated['payload'],
                    $lockVersion,
                    $this->actor($request),
                    ContentSource::Api,
                    $this->apiKey($request),
                    $validated['summary'] ?? null,
                    $validated['identity'] ?? null,
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
}
