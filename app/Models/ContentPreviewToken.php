<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_entry_id',
    'content_revision_id',
    'token_hash',
    'expires_at',
    'revoked_at',
    'created_by',
])]
#[Hidden(['token_hash'])]
class ContentPreviewToken extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ContentEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContentEntry::class, 'content_entry_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
