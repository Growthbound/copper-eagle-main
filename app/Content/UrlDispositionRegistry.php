<?php

namespace App\Content;

/**
 * The consolidation decisions in the URL/intent ledger, made executable.
 *
 * The ledger already records that a handful of generated URLs are duplicates
 * of a migrated pillar and should fold into it. Until something reads that
 * decision, it is a note in a file: the duplicates keep serving 200s and keep
 * appearing in the sitemap, which is the state the ledger exists to prevent.
 *
 * This class is the one place that turns a `consolidate` or `redirect`
 * disposition into behaviour, so the decision and the site cannot disagree.
 * Nothing here invents a hop — a source only forwards because someone recorded
 * a `disposition_target` next to it, and `UrlIntentLedger` has already refused
 * chains and self-references by the time this reads the file.
 *
 * These paths cannot be expressed as managed `redirect_rules`: `services` and
 * `areas` are `ReservedPublicPath` prefixes owned by application routes, and
 * the redirect validator rejects them by design. A structural fold of a
 * code-generated URL belongs in code next to the route that generates it.
 */
final class UrlDispositionRegistry
{
    /**
     * Dispositions that send a visitor somewhere else.
     *
     * Deliberately the same pair `UrlIntentLedger` calls forwarding. A
     * `noindex_hold` still serves its own page and must never appear here.
     */
    private const FORWARDING_DISPOSITIONS = ['consolidate', 'redirect'];

    /**
     * Keyed by path, so a test pointing at a fixture ledger — or at none —
     * cannot be served another one's memo.
     *
     * @var array<string, array<string, string>>
     */
    private static array $forwards = [];

    /**
     * The path is injectable for the same reason `LegacyUrlRegistry`'s is: the
     * mechanism has to be exercisable without depending on whichever ledger a
     * particular site happens to ship.
     */
    public function __construct(private readonly ?string $path = null) {}

    /**
     * Source path => target path, both normalized with a trailing slash.
     *
     * @return array<string, string>
     */
    public function forwards(): array
    {
        $path = $this->path ?? base_path('content/registries/url-intent-ledger.json');

        if (isset(self::$forwards[$path])) {
            return self::$forwards[$path];
        }

        if (! is_file($path)) {
            return self::$forwards[$path] = [];
        }

        $ledger = json_decode((string) file_get_contents($path), true);
        $forwards = [];

        foreach ($ledger['entries'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $url = $entry['url'] ?? null;
            $target = $entry['disposition_target'] ?? null;

            if (! in_array($entry['disposition'] ?? null, self::FORWARDING_DISPOSITIONS, true)
                || ! is_string($url)
                || ! is_string($target)) {
                continue;
            }

            $forwards[PublicUrlSurface::normalize($url)] = PublicUrlSurface::normalize($target);
        }

        return self::$forwards[$path] = $forwards;
    }

    /**
     * Where a path should forward to, or null if it stands on its own.
     */
    public function targetFor(string $path): ?string
    {
        return $this->forwards()[PublicUrlSurface::normalize($path)] ?? null;
    }

    public function isForwarded(string $path): bool
    {
        return $this->targetFor($path) !== null;
    }

    /**
     * Forgotten between tests that rewrite the ledger on disk.
     */
    public static function flush(): void
    {
        self::$forwards = [];
    }
}
