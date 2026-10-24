<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'media_id',
    'content_revision_id',
    'json_pointer',
    'usage_type',
])]
class MediaUsage extends Model
{
    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }
}
