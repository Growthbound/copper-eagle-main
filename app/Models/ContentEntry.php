<?php

namespace App\Models;

use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'kind',
    'slug',
    'title',
    'template',
    'status',
    'published_revision_id',
    'scheduled_revision_id',
    'scheduled_kind',
    'scheduled_slug',
    'scheduled_for',
    'scheduled_by',
    'scheduled_by_api_key_id',
    'published_at',
    'lock_version',
    'created_by',
    'updated_by',
])]
class ContentEntry extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ContentKind::class,
            'template' => PageTemplate::class,
            'status' => ContentStatus::class,
            'scheduled_for' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class);
    }

    /** @return HasOne<ContentRevision, $this> */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(ContentRevision::class)->ofMany('revision_number', 'max');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'published_revision_id');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'scheduled_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function scheduledByApiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'scheduled_by_api_key_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<ContentPreviewToken, $this> */
    public function previewTokens(): HasMany
    {
        return $this->hasMany(ContentPreviewToken::class);
    }

    /** @return HasMany<ContentAuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(ContentAuditEvent::class);
    }
}
