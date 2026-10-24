<?php

namespace App\Models;

use App\Content\Enums\ContentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'content_entry_id',
    'revision_number',
    'schema_version',
    'identity',
    'payload',
    'summary',
    'created_by',
    'api_key_id',
    'source',
])]
class ContentRevision extends Model
{
    protected $attributes = [
        'schema_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'schema_version' => 'integer',
            'identity' => 'array',
            'payload' => 'array',
            'source' => ContentSource::class,
        ];
    }

    /** @return BelongsTo<ContentEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContentEntry::class, 'content_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** @return HasMany<MediaUsage, $this> */
    public function mediaUsages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Content revisions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content revisions are immutable.');
        });
    }
}
