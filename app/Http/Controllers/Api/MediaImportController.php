<?php

namespace App\Http\Controllers\Api;

use App\Enums\MediaKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportMediaRequest;
use App\Models\ApiKey;
use App\Models\Media;
use App\Services\ApiIdempotency;
use App\Services\Media\RemoteMediaCleanupFailed;
use App\Services\Media\RemoteMediaImporter;
use App\ValueObjects\MediaImportItem;
use App\ValueObjects\MediaImportResult;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class MediaImportController extends Controller
{
    public function __construct(private readonly ApiIdempotency $idempotency) {}

    public function __invoke(
        ImportMediaRequest $request,
        RemoteMediaImporter $importer,
    ): Response {
        if ($request->attributes->get('idempotency_managed') === true) {
            return $this->import($request, $importer);
        }

        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            throw new LogicException('Authenticated media imports require an API key.');
        }

        return $this->idempotency->executeExternal(
            $request,
            $key,
            'media.import',
            fn (): JsonResponse => $this->import($request, $importer),
        );
    }

    private function import(
        ImportMediaRequest $request,
        RemoteMediaImporter $importer,
    ): JsonResponse {
        $validated = $request->validated();
        $summary = [
            'requested' => count($validated['items']),
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
        ];
        $items = [];

        foreach ($validated['items'] as $validatedItem) {
            $item = new MediaImportItem(
                sourceUrl: $validatedItem['source_url'],
                title: $validatedItem['title'] ?? null,
                alt: $validatedItem['alt'] ?? null,
                handle: $validatedItem['handle'] ?? null,
            );

            try {
                $result = $importer->import($item);
            } catch (RemoteMediaCleanupFailed) {
                $result = new MediaImportResult(
                    status: 'failed',
                    sourceUrl: $item->sourceUrl,
                    errorCode: 'cleanup_failed',
                    message: 'The failed import may have left an incomplete stored file.',
                );
            }

            if (! array_key_exists($result->status, $summary) || $result->status === 'requested') {
                throw new UnexpectedValueException("Unknown media import status [{$result->status}].");
            }

            $summary[$result->status]++;
            $items[] = $this->resultPayload($result);
        }

        return response()->json([
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    /** @return array<string, mixed> */
    private function resultPayload(MediaImportResult $result): array
    {
        return [
            'status' => $result->status,
            'source_url' => $result->sourceUrl,
            'media' => $result->media === null ? null : $this->mediaPayload($result->media),
            'error_code' => $result->errorCode,
            'message' => $result->message,
        ];
    }

    /** @return array<string, mixed> */
    private function mediaPayload(Media $media): array
    {
        $kind = $media->kind;

        return [
            'id' => $media->getKey(),
            'url' => $media->url(),
            'kind' => $kind instanceof MediaKind ? $kind->value : $kind,
            'mime_type' => $media->mime_type,
            'original_name' => $media->original_name,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->title,
            'alt' => $media->alt,
            'handle' => $media->handle,
        ];
    }
}
