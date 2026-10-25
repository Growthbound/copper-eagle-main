<?php

namespace App\Models;

use App\Content\Enums\ApiKeyScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_id',
    'user_id',
    'name',
    'scopes',
    'secret_hash',
    'last_four',
    'last_used_at',
    'revoked_at',
    'rotated_from_id',
])]
#[Hidden(['secret_hash'])]
class ApiKey extends Model
{
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function rotatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rotated_from_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function displayPrefix(): string
    {
        return "site_live_{$this->public_id}_…{$this->last_four}";
    }

    public function hasScope(ApiKeyScope|string $scope): bool
    {
        $value = $scope instanceof ApiKeyScope ? $scope->value : $scope;

        return in_array($value, $this->scopes ?? [], true);
    }
}
