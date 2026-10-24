<?php

namespace App\Http\Controllers\Api\Content;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Http\Requests\Api\Content\StoreEntryRequest;
use App\Http\Requests\Api\Content\StoreRevisionRequest;
use App\Http\Resources\ContentEntryResource;
use App\Models\ContentEntry;
use App\Services\ApiIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class EntryController extends ContentApiController
{
    public function __construct(
        ApiIdempotency $idempotency,
        private readonly ContentLifecycle $lifecycle,
    ) {
        parent::__construct($idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'kind' => ['nullable', Rule::enum(ContentKind::class)],
            'status' => ['nullable', Rule::enum(ContentStatus::class)],
            'template' => ['nullable', Rule::enum(PageTemplate::class)],
            'slug' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $query = ContentEntry::query()
            ->with('latestRevision')
            ->when(
                isset($validated['kind']),
                fn ($query) => $query->where('kind', $validated['kind']),
            )
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->when(
                isset($validated['template']),
                fn ($query) => $query->where('template', $validated['template']),
            )
            ->when(
                isset($validated['slug']),
                fn ($query) => $query->where('slug', $validated['slug']),
            )
            ->when(isset($validated['search']), function ($query) use ($validated): void {
                $term = $validated['search'];
                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        $paginator = $query->paginate($perPage);
        $paginator->appends(Arr::except($validated, ['page']));

        return response()->json([
            'data' => ContentEntryResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'request_id' => $this->requestId($request),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $entry): JsonResponse
    {
        $content = $this->entry($entry)->load([
            'latestRevision',
            'publishedRevision',
            'scheduledRevision',
        ]);

        return response()
            ->json([
                'data' => ContentEntryResource::make($content)->resolve(),
                'meta' => ['request_id' => $this->requestId($request)],
            ])
            ->header('ETag', $this->etag($content->lock_version));
    }

    public function store(StoreEntryRequest $request): Response
    {
        return $this->executeWrite(
            $request,
            'content.entry.create',
            function () use ($request): JsonResponse {
                $validated = $request->validated();
                $entry = $this->lifecycle->createDraft(
                    $validated['identity'],
                    $validated['payload'],
                    $this->actor($request),
                    $this->apiKey($request),
                )->load('latestRevision');

                return response()
                    ->json([
                        'data' => ContentEntryResource::make($entry)->resolve(),
                        'meta' => ['request_id' => $this->requestId($request)],
                    ], 201)
                    ->header('ETag', $this->etag($entry->lock_version));
            },
        );
    }

    public function validateCandidate(StoreRevisionRequest $request, int $entry): Response
    {
        $content = $this->entry($entry);

        return $this->executeWrite(
            $request,
            "content.entry.{$content->id}.validate",
            function () use ($request, $content): JsonResponse {
                $lockVersion = $this->lockVersion($request);
                if ($lockVersion instanceof JsonResponse) {
                    return $lockVersion;
                }

                $validated = $request->validated();
                $identity = $validated['identity'] ?? [
                    'kind' => $content->kind->value,
                    'slug' => $content->slug,
                    'title' => $content->title,
                    'template' => $content->template->value,
                ];
                $result = $this->lifecycle->validateCandidate(
                    $content,
                    $identity,
                    $validated['payload'],
                    $lockVersion,
                    $this->actor($request),
                    $this->apiKey($request),
                );

                return response()
                    ->json([
                        'data' => $result->toArray(),
                        'meta' => [
                            'lock_version' => $content->lock_version,
                            'request_id' => $this->requestId($request),
                        ],
                    ])
                    ->header('ETag', $this->etag($content->lock_version));
            },
        );
    }
}
