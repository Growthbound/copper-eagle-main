<?php

namespace App\Content;

final class ReservedPublicPath
{
    /**
     * These prefixes are owned by application routes or generated assets.
     * Managed pages and redirects must never claim them.
     *
     * @var array<int, string>
     */
    private const PREFIXES = [
        'admin',
        'api',
        'referrals',
        '_templates',
        'templates',
        'services',
        'areas',
        'media',
        'storage',
        'images',
        'content-preview',
        'livewire',
        'build',
        'up',
        'robots.txt',
        'llms.txt',
        'sitemap.xml',
    ];

    public static function isReserved(string $path): bool
    {
        $normalized = trim($path, '/');

        foreach (self::PREFIXES as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.'/')) {
                return true;
            }

            // Livewire fingerprints its public asset prefix per release.
            if ($prefix === 'livewire'
                && preg_match('/^livewire-[a-z0-9]+(?:\/|$)/i', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
