<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * An outbound destination for site events.
 *
 * The secret is stored readable rather than hashed: unlike an API key, both
 * sides need it to compute the same signature, and the owner has to be able to
 * copy it into the receiving system.
 */
#[Fillable(['name', 'url', 'secret', 'is_active', 'authentication_mode', 'header_name', 'header_secret'])]
class Webhook extends Model
{
    public const AUTH_HMAC = 'hmac';

    public const AUTH_HEADER = 'header';

    public const SIGNATURE_HEADER = 'X-Site-Signature';

    public const TIMESTAMP_HEADER = 'X-Site-Timestamp';

    protected $hidden = ['secret', 'header_secret'];

    protected $attributes = [
        'authentication_mode' => self::AUTH_HMAC,
        'header_name' => self::SIGNATURE_HEADER,
    ];

    public static function validHeaderName(string $name): bool
    {
        return strlen($name) <= 80
            && preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name) === 1
            && ! in_array(strtolower($name), [
                'host', 'content-type', 'content-length', 'connection', 'transfer-encoding',
                'te', 'trailer', 'upgrade', 'expect', 'cookie', 'set-cookie',
                'proxy-authorization', 'proxy-connection',
                strtolower(self::TIMESTAMP_HEADER),
            ], true);
    }

    /** @return array<string, string> */
    public function deliveryHeaders(string $body, int $timestamp): array
    {
        if (! self::validHeaderName($this->header_name)) {
            throw new InvalidArgumentException('Invalid authentication header.');
        }

        if ($this->authentication_mode === self::AUTH_HEADER) {
            $secret = $this->header_secret;

            if (! str_starts_with($this->url, 'https://') || ! is_string($secret)
                || $secret === '' || preg_match('/[^\x20-\x7E]/', $secret)) {
                throw new InvalidArgumentException('Invalid header authentication settings.');
            }

            return [$this->header_name => $secret];
        }

        if ($this->authentication_mode !== self::AUTH_HMAC) {
            throw new InvalidArgumentException('Invalid authentication mode.');
        }

        return [
            $this->header_name => $this->sign($body, $timestamp),
            self::TIMESTAMP_HEADER => (string) $timestamp,
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
            'header_secret' => 'encrypted',
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
