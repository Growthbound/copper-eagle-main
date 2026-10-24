<?php

namespace App\Http\Resources;

use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Models\ContentEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentEntry */
final class ContentEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind instanceof ContentKind ? $this->kind->value : $this->kind,
            'slug' => $this->slug,
            'title' => $this->title,
            'template' => $this->template instanceof PageTemplate
                ? $this->template->value
                : $this->template,
            'status' => $this->status instanceof ContentStatus
                ? $this->status->value
                : $this->status,
            'lock_version' => $this->lock_version,
            'latest_revision' => $this->whenLoaded(
                'latestRevision',
                fn () => ContentRevisionResource::make($this->latestRevision)->resolve(),
            ),
            'published_revision_id' => $this->published_revision_id,
            'scheduled_revision_id' => $this->scheduled_revision_id,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'scheduled_by' => $this->scheduled_by,
            'scheduled_by_api_key_id' => $this->scheduled_by_api_key_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
