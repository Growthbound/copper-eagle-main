<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'content_entry_id',
    'content_revision_id',
    'user_id',
    'api_key_id',
    'action',
    'request_id',
    'metadata',
])]
class ContentAuditEvent extends Model
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Content audit events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content audit events are immutable.');
        });
    }
}
