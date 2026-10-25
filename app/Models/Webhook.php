<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An outbound destination for site events.
 *
 * The secret is stored readable rather than hashed: unlike an API key, both
 * sides need it to compute the same signature, and the owner has to be able to
 * copy it into the receiving system.
 */
#[Fillable(['name', 'url', 'secret', 'is_active'])]
class Webhook extends Model
{
    public const SIGNATURE_HEADER = 'X-Site-Signature';

    public const TIMESTAMP_HEADER = 'X-Site-Timestamp';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /**
     * Signs the exact bytes that will be sent.
     *
     * The timestamp is part of the signed material so a captured request
     * cannot be replayed indefinitely: a receiver can reject anything older
     * than its own tolerance.
     */
    public function sign(string $body, int $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
    }

    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook): void {
            $webhook->secret ??= self::newSecret();
        });
    }
}
