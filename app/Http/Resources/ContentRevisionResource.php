<?php

namespace App\Http\Resources;

use App\Content\Enums\ContentSource;
use App\Models\ContentRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentRevision */
final class ContentRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_entry_id' => $this->content_entry_id,
            'revision_number' => $this->revision_number,
            'schema_version' => $this->schema_version,
            'identity' => $this->identity,
            'payload' => $this->payload,
            'summary' => $this->summary,
            'source' => $this->source instanceof ContentSource
                ? $this->source->value
                : $this->source,
            'created_by' => $this->created_by,
            'api_key_id' => $this->api_key_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
