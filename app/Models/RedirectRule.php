<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'source_path',
    'target',
    'status_code',
    'enabled',
    'notes',
    'created_by',
    'updated_by',
])]
class RedirectRule extends Model
{
    protected $attributes = [
        'status_code' => 301,
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'enabled' => 'boolean',
        ];
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

    /** @return HasMany<RedirectAuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(RedirectAuditEvent::class);
    }
}
