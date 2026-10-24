<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\Validation\ContentValidationResult;
use App\Enums\MediaKind;
use App\Models\Media;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class ContentValidator
{
    /** @var array<int, string> */
    private const FORBIDDEN_KEYS = [
        'html',
        'raw_html',
        'component',
        'view',
        'template_path',
        'css',
        'class',
        'classes',
        'svg',
        'javascript',
        'script',
    ];

    public function __construct(
        private BlockRegistry $blocks,
        private PageTemplateRegistry $templates,
        private BlockData $data,
        private RichContent $richContent,
        private PublicLinkTarget $publicLinks,
        private PageAnchorValidator $anchors,
        private MediaUsageIndexer $mediaUsageIndexer,
        private MediaIndex $mediaIndex,
        private LegacyUrlRegistry $legacy,
    ) {}

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public function validate(
        array $identity,
        array $payload,
        array $unavailablePublicPaths = [],
    ): ContentValidationResult {
        return $this->validateContent(
            $identity,
            $payload,
            forPublication: true,
            unavailablePublicPaths: $unavailablePublicPaths,
        );
    }

    /**
     * Draft persistence may retain a clearly marked scaffold while the owner
     * replaces its fictional examples. Preview and publication remain strict.
     *
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public function validateDraft(
        array $identity,
        array $payload,
        array $unavailablePublicPaths = [],
    ): ContentValidationResult {
        return $this->validateContent(
            $identity,
            $payload,
            forPublication: false,
            unavailablePublicPaths: $unavailablePublicPaths,
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    private function validateContent(
        array $identity,
        array $payload,
        bool $forPublication,
        array $unavailablePublicPaths,
    ): ContentValidationResult {
        $errors = [];
        $warnings = [];
        $kind = $this->enumValue($identity['kind'] ?? null, ContentKind::class);
        $template = $this->enumValue($identity['template'] ?? null, PageTemplate::class);

        if ($kind === null) {
            $errors['/identity/kind'][] = 'The selected content kind is invalid.';
        } elseif (! $kind->isSupported()) {
            $errors['/identity/kind'][] = 'This content kind has no publication consumer and cannot be managed yet.';
        }

        if (! is_string($identity['title'] ?? null)
            || trim($identity['title']) === '') {
            $errors['/identity/title'][] = 'The content title is required.';
        }

        if (! $this->validSlug($identity['slug'] ?? null)) {
            $errors['/identity/slug'][] = 'The content slug must be a safe public path.';
        }

        if ($template === null) {
            $errors['/identity/template'][] = 'The selected page template is invalid.';
        }

        $this->validateSingletonIdentity($kind, $template, $identity, $errors);

        if (! is_string($payload['title'] ?? null)
            || trim($payload['title']) === '') {
            $errors['/title'][] = 'The payload title is required.';
        }

        if (array_key_exists('_scaffold', $payload)
            && ! is_bool($payload['_scaffold'])) {
            $errors['/_scaffold'][] = 'The scaffold marker must be a boolean.';
        } elseif ($forPublication && ($payload['_scaffold'] ?? false) === true) {
            $errors['/_scaffold'][] = 'Replace every fictional scaffold value and remove the scaffold marker before preview or publication.';
        }

        $this->findForbiddenKeys($payload, '', $errors);
        $this->validateMediaReferences($payload, '', $errors);
        $this->validatePageMetadata($payload, $errors);
        $this->validateSiteStructure(
            $kind,
            $payload,
            $errors,
            $unavailablePublicPaths,
        );

        if ($template === PageTemplate::Standard) {
            $this->validateBlocks(
                $payload['blocks'] ?? null,
                $errors,
                $warnings,
                enforceSingleHero: in_array($kind, [
                    ContentKind::Page,
                    ContentKind::Service,
                    ContentKind::Location,
                    ContentKind::Article,
                ], true),
                unavailablePublicPaths: $unavailablePublicPaths,
            );
        } elseif ($template !== null) {
            $result = $this->templates->validate(
                $template,
                $payload,
                $unavailablePublicPaths,
            );
            $errors = array_merge_recursive($errors, $result->errors());
            $warnings = array_merge_recursive($warnings, $result->warnings());
        }

        if ($forPublication && in_array($kind, [
            ContentKind::Page,
            ContentKind::Service,
            ContentKind::Location,
            ContentKind::Article,
        ], true)) {
            $this->validateManagedLinkDestinations(
                $payload,
                '',
                $errors,
                $unavailablePublicPaths,
            );
        }

        return new ContentValidationResult($errors, $warnings);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, string>  $unavailablePublicPaths
     */
    private function validateManagedLinkDestinations(
        array $payload,
        string $pointer,
        array &$errors,
        array $unavailablePublicPaths,
    ): void {
        foreach ($payload as $key => $value) {
            $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
            $valuePointer = $pointer.'/'.$segment;

            if ($key === 'breadcrumbs' && is_array($value) && array_is_list($value)) {
                foreach (array_slice($value, 0, -1, true) as $index => $crumb) {
                    if (is_array($crumb)
                        && array_key_exists('url', $crumb)
                        && ! $this->publicLinks->exists(
                            $crumb['url'],
                            $unavailablePublicPaths,
                        )) {
                        $errors["{$valuePointer}/{$index}/url"][] = 'The internal destination does not exist.';
                    }
                }

                continue;
            }

            if (in_array($key, ['href', 'url'], true)
                && is_string($value)
                && ! $this->publicLinks->exists(
                    $value,
                    $unavailablePublicPaths,
                )) {
                $errors[$valuePointer][] = 'The internal destination does not exist.';
            }

            if (is_array($value)) {
                $this->validateManagedLinkDestinations(
                    $value,
                    $valuePointer,
                    $errors,
                    $unavailablePublicPaths,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateSiteStructure(
        ?ContentKind $kind,
        array $payload,
        array &$errors,
        array $unavailablePublicPaths,
    ): void {
        $collections = match ($kind) {
            ContentKind::Navigation => ['items'],
            ContentKind::Footer => ['company', 'resources'],
            default => [],
        };

        foreach ($collections as $collection) {
            $items = $payload[$collection] ?? null;

            if (! is_array($items) || ! array_is_list($items)) {
                $errors["/{$collection}"][] = 'Links must be an ordered array.';

                continue;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)
                    || ! is_string($item['label'] ?? null)
                    || trim($item['label']) === ''
                    || ! $this->publicLinks->exists(
                        $item['href'] ?? null,
                        $unavailablePublicPaths,
                    )) {
                    $errors["/{$collection}/{$index}"][] = 'Each link needs a label and a destination that exists.';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string>>  $errors
     */
    private function validatePageMetadata(array $payload, array &$errors): void
    {
        if (array_key_exists('noindex', $payload)
            && ! is_bool($payload['noindex'])) {
            $errors['/noindex'][] = 'The noindex field must be a boolean.';
        }

        if (array_key_exists('canonical', $payload)) {
            $canonical = $payload['canonical'];
            $isRootRelative = is_string($canonical)
                && str_starts_with($canonical, '/')
                && ! str_starts_with($canonical, '//')
                && $this->richContent->isSafeUrl($canonical);
            $isHttps = is_string($canonical)
                && str_starts_with(strtolower($canonical), 'https://')
                && $this->richContent->isSafeUrl($canonical);

            if (! $isRootRelative && ! $isHttps) {
                $errors['/canonical'][] = 'The canonical must be a root-relative path or an absolute HTTPS URL.';
            }
        }

        if (array_key_exists('social_image', $payload)
            && $this->data->media($payload['social_image']) === null) {
            $errors['/social_image'][] = 'The social image must include a safe source, alt text, width, and height.';
        }

        if (! array_key_exists('breadcrumbs', $payload)) {
            return;
        }

        if (! is_array($payload['breadcrumbs']) || ! array_is_list($payload['breadcrumbs'])) {
            $errors['/breadcrumbs'][] = 'Breadcrumbs must be an ordered array.';

            return;
        }

        foreach ($payload['breadcrumbs'] as $index => $crumb) {
            if (! is_array($crumb)
                || ! is_string($crumb['name'] ?? null)
                || trim($crumb['name']) === ''
                || ! $this->richContent->isSafeUrl($crumb['url'] ?? null)) {
                $errors["/breadcrumbs/{$index}"][] = 'Each breadcrumb needs a name and safe URL.';
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, array<int, string>>  $warnings
     */
    private function validateBlocks(
        mixed $blocks,
        array &$errors,
        array &$warnings,
        bool $enforceSingleHero = false,
        array $unavailablePublicPaths = [],
    ): void {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $errors['/blocks'][] = 'The standard template needs an ordered blocks array.';

            return;
        }

        $heroCount = 0;
        $firstHeroIndex = null;

        foreach ($blocks as $index => $block) {
            $pointer = "/blocks/{$index}";

            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                $errors["{$pointer}/type"][] = 'Each block needs an allowed type.';

                continue;
            }

            if (! is_array($block['props'] ?? null)) {
                $errors["{$pointer}/props"][] = 'Block props must be an object.';

                continue;
            }

            if (str_starts_with($block['type'], 'hero/')) {
                $heroCount++;
                $firstHeroIndex ??= $index;
            }

            $result = $this->blocks->validate(
                $block['type'],
                $block['props'],
                $unavailablePublicPaths,
            );
            $this->prefixMessages($errors, $result->errors(), $pointer);
            $this->prefixMessages($warnings, $result->warnings(), $pointer);
        }

        if ($enforceSingleHero && $heroCount > 1) {
            $errors['/blocks'][] = 'A standard page may contain at most one H1-owning hero block.';
        }

        if ($enforceSingleHero && $heroCount === 1 && $firstHeroIndex !== 0) {
            $errors["/blocks/{$firstHeroIndex}/type"][] = 'An H1-owning hero must be the first block on a standard page.';
        }

        $this->validateEffectiveAnchors($blocks, $errors);
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateSingletonIdentity(
        ?ContentKind $kind,
        ?PageTemplate $template,
        array $identity,
        array &$errors,
    ): void {
        $requiredSlug = match ($kind) {
            ContentKind::Navigation => 'primary',
            ContentKind::Footer => 'site-footer',
            default => null,
        };

        if ($requiredSlug === null) {
            return;
        }

        if (($identity['slug'] ?? null) !== $requiredSlug) {
            $errors['/identity/slug'][] = "This content kind must use the {$requiredSlug} singleton identity.";
        }

        if ($template !== PageTemplate::Standard) {
            $errors['/identity/template'][] = 'Navigation and footer content must use the standard template.';
        }
    }

    /**
     * Validate the anchors the Blade components actually emit, including
     * defaults, so duplicate ids and dead table-of-contents links cannot be
     * published.
     *
     * @param  array<int, mixed>  $blocks
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateEffectiveAnchors(array $blocks, array &$errors): void
    {
        $sections = [];

        foreach ($blocks as $index => $block) {
            if (is_array($block)
                && is_string($block['type'] ?? null)
                && is_array($block['props'] ?? null)) {
                $sections[] = [
                    'type' => $block['type'],
                    'props' => $block['props'],
                    'pointer' => "/blocks/{$index}/props",
                ];
            }
        }

        $this->prefixMessages($errors, $this->anchors->validate($sections)->errors(), '');
    }

    /**
     * @param  array<string, array<int, string>>  $target
     * @param  array<string, array<int, string>>  $messages
     */
    private function prefixMessages(array &$target, array $messages, string $prefix): void
    {
        foreach ($messages as $pointer => $items) {
            $target[$prefix.$pointer] = array_merge(
                $target[$prefix.$pointer] ?? [],
                $items,
            );
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function findForbiddenKeys(mixed $value, string $pointer, array &$errors): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $segment = $this->pointerSegment((string) $key);
            $childPointer = $pointer.'/'.$segment;

            $normalizedKey = is_string($key) ? strtolower($key) : '';

            if (is_string($key)
                && (in_array($normalizedKey, self::FORBIDDEN_KEYS, true)
                    || $normalizedKey === 'style'
                    || str_starts_with($normalizedKey, 'on'))) {
                $errors[$childPointer][] = 'Managed content cannot choose markup, code, classes, or component paths.';
            }

            $this->findForbiddenKeys($child, $childPointer, $errors);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateMediaReferences(
        mixed $value,
        string $pointer,
        array &$errors,
    ): void {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $childPointer = $pointer.'/'.$this->pointerSegment((string) $key);

            if ($key === 'media_id') {
                // Both reference forms resolve the same way: this install's
                // catalog first, then the versioned media index. They used to
                // disagree, which took the four service pages off any install
                // whose media table was empty.
                if (! is_int($child) || $child < 1) {
                    $errors[$childPointer][] = 'The selected media item does not exist.';
                } elseif ($this->mediaCatalogAvailable()
                    && ! Media::query()->whereKey($child)->exists()
                    && $this->mediaIndex->find($child) === null) {
                    $errors[$childPointer][] = 'The selected media item does not exist, and the media index does not describe item '.$child.'.';
                }

                continue;
            }

            if (is_string($child)
                && in_array($key, ['src', 'video_url'], true)
                && ($catalogId = $this->mediaUsageIndexer->catalogMediaId($child)) !== null) {
                $media = $this->mediaCatalogAvailable() ? Media::query()->find($catalogId) : null;
                $expectsVideo = $key === 'video_url'
                    || ($key === 'src' && str_ends_with($pointer, '/video'));

                // This install's catalog answers first. When it has nothing to
                // say, the versioned media index does — that is what lets the
                // library live on another install without copying binaries here.
                if ($media === null) {
                    if ($this->mediaIndex->find($catalogId) === null) {
                        $errors[$childPointer][] = 'The selected media item does not exist, and the media index does not describe item '.$catalogId.'.';
                    } elseif ($expectsVideo) {
                        $errors[$childPointer][] = 'The media index only describes still images; item '.$catalogId.' cannot be used as a video.';
                    }
                } else {
                    $expectedKind = $key === 'video_url'
                        || ($key === 'src' && str_ends_with($pointer, '/video'))
                            ? MediaKind::Video
                            : MediaKind::Image;

                    if ($media->kind !== $expectedKind) {
                        $errors[$childPointer][] = 'The selected media item must be a '.$expectedKind->value.'.';
                    }
                }
            }

            $this->validateMediaReferences($child, $childPointer, $errors);
        }
    }

    private function mediaCatalogAvailable(): bool
    {
        try {
            return Schema::hasTable('media');
        } catch (Throwable) {
            return false;
        }
    }

    private function validSlug(mixed $slug): bool
    {
        if (! is_string($slug)
            || $slug === ''
            || preg_match('/[\x00-\x20\x7F?#\\\\]/', $slug) === 1) {
            return false;
        }

        $normalized = trim($slug, '/');

        if ($normalized === '') {
            return $slug === '/';
        }

        if (preg_match('/^[a-z0-9]+(?:[\\/-][a-z0-9]+)*$/', $normalized) !== 1) {
            return false;
        }

        if (ReservedPublicPath::isReserved($normalized)) {
            return false;
        }

        /*
         * A path a legacy family already claims cannot hold a page.
         * `ApplyLegacyWordPressUrls` runs before the page fallback, so a
         * revision published at `feed`, `author/new` or `blog/tag/new` would
         * validate, preview correctly, and then 301 away from its own public
         * URL — visible nowhere except by visiting it.
         *
         * Checked here rather than added to `ReservedPublicPath`, because
         * those prefixes are platform property and these families are one
         * site's migration history. Another brand on this platform may
         * legitimately own `/feed/`.
         *
         * Both slash forms, since the middleware keys on the request path and
         * a page is reachable with or without the trailing one.
         */
        return $this->legacy->targetFor('/'.$normalized) === null
            && $this->legacy->targetFor('/'.$normalized.'/') === null;
    }

    private function pointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function enumValue(mixed $value, string $enum): ?\BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        return is_string($value) ? $enum::tryFrom($value) : null;
    }
}
