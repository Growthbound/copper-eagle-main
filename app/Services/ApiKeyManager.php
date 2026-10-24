<?php

namespace App\Services;

use App\Content\Enums\ApiKeyScope;
use App\Models\ApiKey;
use App\Models\User;
use App\ValueObjects\IssuedApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class ApiKeyManager
{
    /**
     * @param  array<int, ApiKeyScope|string>  $scopes
     */
    public function create(
        User $user,
        string $name,
        array $scopes = [ApiKeyScope::MediaImport],
    ): IssuedApiKey {
        return $this->issue($user, $name, $this->normalizeScopes($scopes));
    }

    public function rotate(ApiKey $key): IssuedApiKey
    {
        return DB::transaction(function () use ($key): IssuedApiKey {
            $source = ApiKey::query()->lockForUpdate()->findOrFail($key->getKey());

            if ($source->isRevoked()) {
                throw new LogicException('Revoked API keys cannot be rotated.');
            }

            $replacement = $this->issue(
                $source->user,
                $source->name,
                $this->normalizeScopes($source->scopes ?? []),
                $source->getKey(),
            );

            $source->forceFill(['revoked_at' => now()])->save();

            return $replacement;
        });
    }

    public function revoke(ApiKey $key): void
    {
        DB::transaction(function () use ($key): void {
            $stored = ApiKey::query()->lockForUpdate()->findOrFail($key->getKey());

            if (! $stored->isRevoked()) {
                $stored->forceFill(['revoked_at' => now()])->save();
            }
        });
    }

    /**
     * @param  array<int, ApiKeyScope|string>  $scopes
     */
    public function updateScopes(ApiKey $key, array $scopes): ApiKey
    {
        return DB::transaction(function () use ($key, $scopes): ApiKey {
            $stored = ApiKey::query()->lockForUpdate()->findOrFail($key->getKey());

            if ($stored->isRevoked()) {
                throw new LogicException('Revoked API key scopes cannot be changed.');
            }

            $stored->forceFill([
                'scopes' => $this->normalizeScopes($scopes),
            ])->save();

            return $stored->fresh();
        });
    }

    public function authenticate(string $email, string $plaintext): ?ApiKey
    {
        $key = $this->resolve($email, $plaintext);

        if ($key !== null) {
            $this->recordUsage($key);
        }

        return $key;
    }

    public function resolve(string $email, string $plaintext): ?ApiKey
    {
        if (! preg_match('/^site_live_([a-z0-9]{16})_([a-f0-9]{64})$/D', $plaintext, $matches)) {
            return null;
        }

        $key = ApiKey::query()
            ->with('user')
            ->where('public_id', $matches[1])
            ->whereNull('revoked_at')
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [Str::lower($email)]))
            ->first();

        if ($key === null || ! hash_equals($key->secret_hash, hash('sha256', $matches[2]))) {
            return null;
        }

        return $key;
    }

    public function recordUsage(ApiKey $key): void
    {
        $key->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function issue(
        User $user,
        string $name,
        array $scopes,
        ?int $rotatedFromId = null,
    ): IssuedApiKey {
        do {
            $publicId = Str::lower(Str::random(16));
        } while (ApiKey::query()->where('public_id', $publicId)->exists());

        $secret = bin2hex(random_bytes(32));

        $key = ApiKey::query()->create([
            'public_id' => $publicId,
            'user_id' => $user->getKey(),
            'name' => trim($name),
            'scopes' => $scopes,
            'secret_hash' => hash('sha256', $secret),
            'last_four' => substr($secret, -4),
            'rotated_from_id' => $rotatedFromId,
        ]);

        return new IssuedApiKey($key, "site_live_{$publicId}_{$secret}");
    }

    /**
     * @param  array<int, ApiKeyScope|string>  $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];

        foreach ($scopes as $scope) {
            $value = $scope instanceof ApiKeyScope
                ? $scope->value
                : (is_string($scope) ? $scope : '');
            $enum = ApiKeyScope::tryFrom($value);

            if (! $enum instanceof ApiKeyScope) {
                throw new LogicException("Unknown API key scope [{$value}].");
            }

            if (! in_array($enum->value, $normalized, true)) {
                $normalized[] = $enum->value;
            }
        }

        return $normalized;
    }
}
