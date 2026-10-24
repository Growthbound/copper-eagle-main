<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'api_key_id',
    'operation',
    'idempotency_key_hash',
    'request_hash',
    'response_status',
    'response_body',
    'response_body_ciphertext',
    'response_headers',
    'expires_at',
])]
#[Hidden(['idempotency_key_hash', 'request_hash', 'response_body_ciphertext'])]
class ApiIdempotencyRecord extends Model
{
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'response_headers' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
