<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'redirect_rule_id',
    'user_id',
    'action',
    'request_id',
    'before',
    'after',
])]
class RedirectAuditEvent extends Model
{
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /** @return BelongsTo<RedirectRule, $this> */
    public function redirect(): BelongsTo
    {
        return $this->belongsTo(RedirectRule::class, 'redirect_rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Redirect audit events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Redirect audit events are immutable.');
        });
    }
}
