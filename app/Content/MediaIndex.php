<?php

namespace App\Content;

use RuntimeException;

/**
 * What every image in the media library actually is.
 *
 * Content references media by its stable id — `/media/43` — and never by a
 * hostname, so the same document renders against a local server, the dev site,
 * or production depending on `MEDIA_BASE_URL`. Moving the library does not
 * touch a single page.
 *
 * The second job matters more over time. A media library that only records
 * dimensions and a filename forces every future editor to open each image and
 * guess whether it is a real job photo or a stock illustration. That guess is
 * how stock photography ends up presented as first-party proof. This index
 * records what an asset shows, where it came from, and — honestly — whether
 * anyone has actually looked at it. An asset marked `catalog_only` is not
 * allowed to carry alt text, because alt text nobody verified is a claim about
 * an image nobody has seen.
 */
final class MediaIndex
{
    /**
     * Where an asset came from, which decides what it may be used to prove.
     *
     * Only `first_party` is evidence. `stock` is decoration and must never
     * appear in a proof, case-study, or before/after context.
     */
    private const PROVENANCE = ['first_party', 'stock', 'brand_asset', 'third_party_logo', 'unknown'];

    /**
     * What the image can be used to show, which is a different question from
     * where it came from.
     *
     * A landmark photo of a city is not evidence that work was done there, and
     * a stock photo of a technician is not evidence of this technician. Only
     * the first four classes may be used as proof, and the validator refuses to
     * let anything that is not first-party claim them.
     */
    private const EVIDENCE_CLASS = [
        'job_documentation',
        'before_after',
        'equipment',
        'team',
        'location',
        'illustration',
        'decorative',
        'logo',
    ];

    /**
     * Classes that assert "this is our work", and so require a first-party
     * origin. This is the rule that stops stock photography being presented as
     * proof three refactors from now.
     */
    private const PROOF_CLASSES = ['job_documentation', 'before_after', 'equipment', 'team'];

    private const VERIFIED = ['analyzed', 'catalog_only'];

    /**
     * Keyed by path, so a test pointing at a fixture index — or at none —
     * cannot be served another one's memo.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $assets = [];

    /**
     * Original WordPress filename, lowercased, to library id, keyed by path.
     *
     * @var array<string, array<string, int>>
     */
    private static array $byOriginalName = [];

    /**
     * The path is injectable so the lookups can be exercised against a fixture
     * index rather than whichever library a particular site happens to ship.
     */
    public function __construct(private readonly ?string $path = null) {}

    /**
     * Only for tests that swap the index out from under a running process.
     */
    public static function flush(): void
    {
        self::$assets = [];
        self::$byOriginalName = [];
    }

    private function path(): string
    {
        return $this->path ?? base_path('content/registries/media-index.json');
    }

    /**
     * The library id a reference points at, or null if it is not a library
     * reference at all.
     *
     * Only a bare id is a library reference. `/media/brand/photo.jpg`
     * is a file in this repository and stays one.
     */
    public static function referencedId(string $src): ?int
    {
        return preg_match('#^/media/([1-9][0-9]*)/?$#D', $src, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * Whether the library lives on another install.
     *
     * When it does, `/media/{id}` is not this catalog's item and cannot be
     * validated against this database; the versioned index answers instead.
     */
    public function isExternal(): bool
    {
        return trim((string) config('media.base_url')) !== '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function assets(): array
    {
        $path = $this->path();

        if (isset(self::$assets[$path])) {
            return self::$assets[$path];
        }

        if (! is_file($path)) {
            return self::$assets[$path] = [];
        }

        $index = json_decode((string) file_get_contents($path), true);
        $assets = [];

        foreach ($index['assets'] ?? [] as $asset) {
            if (is_array($asset) && is_int($asset['id'] ?? null)) {
                $assets[$asset['id']] = $asset;
            }
        }

        return self::$assets[$path] = $assets;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->assets()[$id] ?? null;
    }

    /**
     * The library id for a file as it was named on the WordPress site.
     *
     * Every asset here was imported from that library and kept its
     * `original_name`, which makes the old `/wp-content/uploads/` URLs
     * mechanically resolvable to their new ids instead of becoming 404s the day
     * the site cuts over.
     *
     * WordPress also publishes generated sizes — `photo-300x200.jpg` — as their
     * own URLs, and those get indexed and hotlinked like any other. Only the
     * original was imported, so a size suffix falls back to it: the visitor
     * wanted this picture, and a slightly larger one beats nothing.
     *
     * Large uploads get a third name. WordPress stores a downscaled
     * `photo-scaled.jpg` as the working file while its pages and generated
     * sizes still reference `photo.jpg`, so the library records one name and
     * the indexed URLs use another. Both resolve to the same asset.
     */
    public function idForOriginalName(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $path = $this->path();

        if (! isset(self::$byOriginalName[$path])) {
            $byName = [];

            /*
             * A library can hold an image imported twice under the
             * same filename, so a name can point at more than one id. The
             * lowest wins — compared rather than taken first, so reordering the
             * registry cannot silently repoint a permanent redirect. Both
             * copies are the same picture, so the visitor cannot tell.
             */
            foreach ($this->assets() as $asset) {
                $key = strtolower(trim((string) ($asset['original_name'] ?? '')));
                $id = (int) $asset['id'];

                if ($key !== '' && $id < ($byName[$key] ?? PHP_INT_MAX)) {
                    $byName[$key] = $id;
                }
            }

            self::$byOriginalName[$path] = $byName;
        }

        foreach (self::candidateNames($name) as $candidate) {
            if ($id = self::$byOriginalName[$path][strtolower($candidate)] ?? null) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Every library name a public upload URL might have been stored under.
     *
     * @return list<string>
     */
    private static function candidateNames(string $name): array
    {
        $candidates = [$name];

        $withoutSize = preg_replace('/-\d+x\d+(?=\.[A-Za-z0-9]+$)/', '', $name);

        if (is_string($withoutSize) && $withoutSize !== $name) {
            $candidates[] = $withoutSize;
        }

        foreach ($candidates as $candidate) {
            $scaled = preg_replace('/(?=\.[A-Za-z0-9]+$)/', '-scaled', $candidate, 1);

            if (is_string($scaled) && ! in_array($scaled, $candidates, true)) {
                $candidates[] = $scaled;
            }
        }

        return $candidates;
    }

    /**
     * Rewrite a library reference against the configured library base.
     *
     * Only a bare library id — `/media/43` — is a library reference. Repository
     * files served from `/media/brand/…` belong to this install and are
     * left alone; rewriting those would point them at a host that has never
     * heard of them. Build assets are likewise untouched.
     */
    public static function resolve(string $src, ?string $baseUrl): string
    {
        if (preg_match('#^/media/\d+/?$#', $src) !== 1) {
            return $src;
        }

        if ($baseUrl === null || trim($baseUrl) === '') {
            return $src;
        }

        return rtrim($baseUrl, '/').'/'.trim($src, '/');
    }

    /**
     * @param  array<string, mixed>  $index
     * @return list<string>
     */
    public function validate(array $index): array
    {
        $errors = [];

        if (($index['schema_version'] ?? null) !== 1) {
            $errors[] = 'The media index schema_version must be 1.';
        }

        $rights = $index['rights'] ?? null;

        if (! is_array($rights)
            || ! is_string($rights['holder'] ?? null)
            || ! is_string($rights['status'] ?? null)
            || ! is_string($rights['confirmed_on'] ?? null)) {
            $errors[] = 'The media index rights must record a holder, a status and a confirmation date.';
        }

        $assets = $index['assets'] ?? null;

        if (! is_array($assets) || ! array_is_list($assets)) {
            return [...$errors, 'The media index assets must be a list.'];
        }

        $ids = [];
        $duplicates = [];

        foreach ($assets as $position => $asset) {
            $pointer = "assets.{$position}";

            if (! is_array($asset)) {
                $errors[] = "{$pointer} must be an object.";

                continue;
            }

            $id = $asset['id'] ?? null;

            if (! is_int($id) || $id < 1) {
                $errors[] = "{$pointer}.id must be a positive integer.";

                continue;
            }

            if (isset($ids[$id])) {
                $errors[] = "{$pointer}.id {$id} appears more than once, first at {$ids[$id]}.";

                continue;
            }

            $ids[$id] = $pointer;

            if (($asset['path'] ?? null) !== '/media/'.$id) {
                $errors[] = "{$pointer}.path must be /media/{$id}.";
            }

            if (! is_string($asset['original_name'] ?? null) || trim((string) $asset['original_name']) === '') {
                $errors[] = "{$pointer}.original_name is required.";
            }

            $width = $asset['width'] ?? null;
            $height = $asset['height'] ?? null;

            if (! is_int($width) || ! is_int($height) || $width < 1 || $height < 1) {
                $errors[] = "{$pointer}.width and height must be positive integers.";
            }

            $provenance = $asset['provenance'] ?? null;

            if (! is_string($provenance) || ! in_array($provenance, self::PROVENANCE, true)) {
                $errors[] = "{$pointer}.provenance must be one of ".implode(', ', self::PROVENANCE).'.';
            }

            $evidenceClass = $asset['evidence_class'] ?? null;

            if (! is_string($evidenceClass) || ! in_array($evidenceClass, self::EVIDENCE_CLASS, true)) {
                $errors[] = "{$pointer}.evidence_class must be one of ".implode(', ', self::EVIDENCE_CLASS).'.';
            } elseif (in_array($evidenceClass, self::PROOF_CLASSES, true) && $provenance !== 'first_party') {
                $errors[] = "{$pointer} is classed as {$evidenceClass}, which only first-party media may claim; its provenance is ".json_encode($provenance).'.';
            }

            $verified = $asset['verified'] ?? null;

            if (! is_string($verified) || ! in_array($verified, self::VERIFIED, true)) {
                $errors[] = "{$pointer}.verified must be one of ".implode(', ', self::VERIFIED).'.';

                continue;
            }

            $alt = $asset['alt'] ?? null;
            $description = $asset['description'] ?? null;
            $subject = $asset['subject'] ?? null;

            if ($verified === 'analyzed') {
                if (! is_string($alt) || trim($alt) === '') {
                    $errors[] = "{$pointer}.alt is required once an asset is analyzed.";
                }

                if (! is_string($description) || trim($description) === '') {
                    $errors[] = "{$pointer}.description is required once an asset is analyzed.";
                }

                if (! is_array($subject) || $subject === []) {
                    $errors[] = "{$pointer}.subject is required once an asset is analyzed.";
                }
            } elseif (filled($alt) || filled($description)) {
                $errors[] = "{$pointer} must not carry alt text or a description until it is analyzed.";
            }

            if (array_key_exists('duplicate_of', $asset) && $asset['duplicate_of'] !== null) {
                $duplicates[$pointer] = $asset['duplicate_of'];
            }
        }

        foreach ($duplicates as $pointer => $target) {
            if (! is_int($target) || ! isset($ids[$target])) {
                $errors[] = "{$pointer} declares duplicate_of ".json_encode($target).', which is not in the index.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $index
     */
    public function assertValid(array $index): void
    {
        $errors = $this->validate($index);

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
    }
}
