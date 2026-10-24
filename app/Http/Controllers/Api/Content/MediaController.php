<?php

namespace App\Http\Controllers\Api\Content;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Services\ApiIdempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MediaController extends ContentApiController
{
    public function __construct(ApiIdempotency $idempotency)
    {
        parent::__construct($idempotency);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:200'],
            'kind' => ['nullable', Rule::enum(MediaKind::class)],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $paginator = Media::query()
            ->search($validated['search'] ?? null)
            ->when(
                isset($validated['kind']),
                fn ($query) => $query->where('kind', $validated['kind']),
            )
            ->latest('id')
            ->paginate($perPage);
        $items = collect($paginator->items())
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'url' => $media->url(),
                'kind' => $media->kind instanceof MediaKind
                    ? $media->kind->value
                    : $media->kind,
                'mime_type' => $media->mime_type,
                'original_name' => $media->original_name,
                'size' => $media->size,
                'width' => $media->width,
                'height' => $media->height,
                'title' => $media->title,
                'alt' => $media->alt,
                'handle' => $media->handle,
            ])
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'request_id' => $this->requestId($request),
            ],
        ]);
    }
}
