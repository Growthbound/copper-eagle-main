<?php

namespace App\Content;

use JsonException;

/**
 * The URL families a previous CMS published, declared as site data.
 *
 * The families themselves are not platform behaviour. `/blog/` is this site's
 * archive and `/feed/` was this site's feed; another brand on this platform may
 * serve a different archive, or own `/feed/` for something else entirely.
 * Hardcoding one site's migration into shared middleware would install that
 * site's policy on every instance, so the destinations live in
 * `content/registries/legacy-urls.json` and this class only knows how to read
 * one. A repository with no registry gets no legacy redirects at all.
 *
 * Uploads are the exception that proves the rule: the *mechanism* — resolve an
 * old filename through the media index — is portable, so it stays in code. The
 * prefix it applies to is declared, because not every CMS serves media from
 * `/wp-content/uploads/`.
 */
final class LegacyUrlRegistry
{
    /**
     * Keyed by path, so a test pointing at a different registry — or at none —
     * cannot be served another one's memo.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $registries = [];

    /**
     * The path is injectable so the no-registry case, which is what portability
     * to another brand actually means, can be exercised rather than asserted.
     */
    public function __construct(
        private readonly MediaIndex $media,
        private readonly ?string $path = null,
        private readonly ?UrlDispositionRegistry $dispositions = null,
    ) {}

    public static function flush(): void
    {
        self::$registries = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $path = $this->path ?? base_path('content/registries/legacy-urls.json');

        if (isset(self::$registries[$path])) {
            return self::$registries[$path];
        }

        if (! is_file($path)) {
            return self::$registries[$path] = [];
        }

        /*
         * Throws rather than falling back to an empty registry. A truncated or
         * malformed file would otherwise disable every legacy redirect in
         * production while the validation test, seeing no registry at all,
         * reported no errors.
         */
        $registry = json_decode(
            (string) file_get_contents($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        /*
         * `null`, `false` and a bare string all decode without error and would
         * otherwise become an empty registry, which is indistinguishable from
         * the supported case of having no registry at all. A file that exists
         * and is not an object is a mistake, and should say so rather than
         * quietly disabling every legacy redirect.
         *
         * `[]` needs the list check to catch it. Associative decoding turns a
         * JSON array into a PHP array, so `is_array()` alone waves it through
         * and it becomes that same silent empty registry — the exact failure
         * this guard exists to prevent, arriving through the one JSON type the
         * guard could not see.
         */
        if (! is_array($registry) || array_is_list($registry)) {
            throw new JsonException(
                "The legacy URL registry at {$path} must contain a JSON object.",
            );
        }

        return self::$registries[$path] = $registry;
    }

    /**
     * Where a legacy path should forward to, or null if this application has
     * nothing to say about it.
     */
    public function targetFor(string $path): ?string
    {
        $registry = $this->all();

        if ($registry === []) {
            return null;
        }

        $exact = $registry['exact'] ?? [];

        if (is_array($exact) && is_string($exact[$path] ?? null)) {
            return $exact[$path];
        }

        foreach ($registry['patterns'] ?? [] as $family) {
            if (! is_array($family)
                || ! is_string($family['match'] ?? null)
                || ! is_string($family['target'] ?? null)) {
                continue;
            }

            if (preg_match('#'.$family['match'].'#', $path) === 1) {
                return $family['target'];
            }
        }

        return $this->uploadTarget($path);
    }

    public function forwards(string $path): bool
    {
        return $this->targetFor($path) !== null;
    }

    /**
     * An uploaded file, resolved to wherever the library actually lives.
     *
     * Two things have to be right here. Media excluded from the index — the
     * homepage hero video is not a still image and so is not catalogued — still
     * has a public URL that is linked from the live site, and is answered from
     * the declared file map. And when the library is hosted elsewhere,
     * `/media/{id}` on this host may resolve against an empty table, so the id
     * is rewritten against the configured base exactly as page content is.
     */
    private function uploadTarget(string $path): ?string
    {
        $registry = $this->all();

        foreach ($registry['upload_prefixes'] ?? [] as $prefix) {
            if (! is_string($prefix) || ! str_starts_with($path, $prefix)) {
                continue;
            }

            // WordPress switches uploads to year/month folders on a settings
            // change, and URLs from before such a change stay indexed.
            $name = preg_replace('#^(?:\d{4}/\d{2}/)?#', '', substr($path, strlen($prefix)));

            if ($name === null || $name === '' || str_contains($name, '/')) {
                return null;
            }

            $files = $registry['files'] ?? [];

            if (is_array($files) && is_string($files[$name] ?? null)) {
                return $files[$name];
            }

            $id = $this->media->idForOriginalName($name);

            return $id === null
                ? null
                : MediaIndex::resolve('/media/'.$id, config('media.base_url'));
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $registry = $this->all();
        $errors = [];

        if ($registry === []) {
            return $errors;
        }

        if (($registry['schema_version'] ?? null) !== 1) {
            $errors[] = 'The legacy URL registry schema_version must be 1.';
        }

        foreach ($registry['exact'] ?? [] as $source => $target) {
            if (! is_string($source) || ! str_starts_with($source, '/')) {
                $errors[] = 'Each exact source must be an absolute path.';
            }

            if (! is_string($target) || ! str_starts_with($target, '/')) {
                $errors[] = "The target for {$source} must be an absolute path.";
            }
        }

        /*
         * The upload fields went unchecked, which made this validator claim
         * more than it verified. `uploadTarget()` skips anything malformed
         * silently, so a mistyped prefix or a numeric file target produced a
         * registry that passed its structural test while the uploads it
         * declares answered 404. A check that is quiet about the shapes it
         * never looks at reads as coverage.
         */
        if (array_key_exists('upload_prefixes', $registry)) {
            if (! is_array($registry['upload_prefixes'])
                || ! array_is_list($registry['upload_prefixes'])) {
                $errors[] = 'upload_prefixes must be a list.';
            } else {
                foreach ($registry['upload_prefixes'] as $index => $prefix) {
                    if (! is_string($prefix) || ! str_starts_with($prefix, '/')) {
                        $errors[] = "upload_prefixes.{$index} must be an absolute path.";
                    }
                }
            }
        }

        if (array_key_exists('files', $registry)) {
            if (! is_array($registry['files']) || array_is_list($registry['files'])) {
                $errors[] = 'files must be an object keyed by original filename.';
            } else {
                foreach ($registry['files'] as $name => $target) {
                    if (! is_string($name) || trim($name) === '') {
                        $errors[] = 'Each files key must be an original filename.';
                    }

                    if (! is_string($target) || ! str_starts_with($target, '/')) {
                        $errors[] = "The files target for {$name} must be an absolute path.";
                    }
                }
            }
        }

        foreach ($registry['patterns'] ?? [] as $index => $family) {
            $pointer = "patterns.{$index}";

            if (! is_array($family)) {
                $errors[] = "{$pointer} must be an object.";

                continue;
            }

            foreach (['match', 'target', 'note'] as $field) {
                if (! is_string($family[$field] ?? null) || trim((string) $family[$field]) === '') {
                    $errors[] = "{$pointer}.{$field} is required.";
                }
            }

            $match = $family['match'] ?? null;

            if (is_string($match)) {
                if (@preg_match('#'.$match.'#', '') === false) {
                    $errors[] = "{$pointer}.match is not a valid expression.";
                } elseif (! str_starts_with($match, '^')) {
                    // An unanchored pattern matches mid-path and quietly
                    // captures URLs nobody declared.
                    $errors[] = "{$pointer}.match must be anchored at the start.";
                }
            }

            if (is_string($family['target'] ?? null) && ! str_starts_with($family['target'], '/')) {
                $errors[] = "{$pointer}.target must be an absolute path.";
            }
        }

        /*
         * A family whose target is itself a legacy source spends two redirects
         * saying one thing, and search engines discount the chain.
         *
         * Both authorities have to be asked. Three systems serve redirects here
         * and each one's own chain check sees only its own rules, so a legacy
         * target that the ledger consolidates is a two-hop chain that neither
         * side notices on its own — the legacy middleware forwards, then the
         * ledger middleware forwards again. Checking only this registry finds
         * the shape of the bug while missing the other half of it.
         */
        $dispositions = $this->dispositions ?? app(UrlDispositionRegistry::class);

        foreach ($this->declaredTargets() as $target) {
            if ($this->targetFor($target) !== null) {
                $errors[] = "{$target} is a legacy destination and also a legacy source, which is a chain.";
            }

            if ($dispositions->isForwarded($target)) {
                $errors[] = "{$target} is a legacy destination and is also forwarded by the intent ledger, which is a chain.";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function declaredTargets(): array
    {
        $registry = $this->all();
        $targets = array_values(array_filter((array) ($registry['exact'] ?? []), 'is_string'));

        foreach ($registry['patterns'] ?? [] as $family) {
            if (is_array($family) && is_string($family['target'] ?? null)) {
                $targets[] = $family['target'];
            }
        }

        return array_values(array_unique($targets));
    }
}
