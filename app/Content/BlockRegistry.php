<?php

namespace App\Content;

use App\Content\Definitions\BlockDefinition;
use App\Content\Validation\ContentValidationResult;
use App\Support\MarketingIcon;
use InvalidArgumentException;

final class BlockRegistry
{
    /** @var array<string, string|null> */
    private const DEFAULT_ANCHORS = [
        'content/benefit-carousel' => 'benefits',
        'media/video' => 'video',
        'process-steps/icon-grid' => 'process-icon-grid',
        'process-steps/grid' => 'process',
        'photo-grid/gallery' => 'photo-gallery',
        'logo-strip/credentials' => 'credentials',
        'faq/highlighted' => 'faq-highlighted',
        'cta/quote-form' => 'quote',
        'faq/accordion' => 'faq',
        'cta/quote-form-split' => 'quote',
        'team/grid' => 'team-grid',
        'hero/service' => 'service-hero',
        'cta/sticky-mobile' => 'sticky-mobile-cta',
        'hero/interior' => 'interior-hero',
        'cta/image-split' => 'image-split-cta',
        'hero/location' => 'location-hero',
        'service-cards/grid' => 'services',
        'evidence/case-study' => 'case-study',
        'evidence/local-project' => 'local-project',
        'evidence/check-ins' => 'check-ins',
        'testimonials/quotes' => 'testimonials',
        'content/split' => null,
        'cta/panel' => 'cta',
        'testimonials/carousel' => 'testimonial-carousel',
        'content/table-of-contents' => 'table-of-contents',
        'stats/proof' => 'proof-stats',
        'service-areas/list' => 'service-areas',
        'before-after/slider' => 'before-after',
        'cta/form-confirmation' => 'form-confirmation',
        'content/resource-cards' => 'resources',
        'content/inclusions-exclusions' => 'scope',
        'content/rich-text' => 'rich-content',
        'hero/article' => 'article-hero',
        'cta/visit-expectations' => 'visit-expectations',
        'cta/inline-band' => 'inline-cta',
        'pricing/toggle' => 'pricing',
        'content/related-content' => 'related-content',
        'service-areas/grid' => 'service-area-grid',
        'pricing/yearly' => 'pricing',
        'content/sources-reviewer' => 'sources-reviewer',
        'content/commitment-collage' => 'commitment',
        'content/comparison-table' => 'comparison',
        'service-areas/coverage' => 'coverage',
        'pricing/monthly' => 'pricing',
        'content/story-checklist' => 'story-checklist',
        'content/direct-answer' => 'direct-answer',
        'pricing/factors' => 'pricing-factors',
        'hero/photo' => 'hero',
        'service-cards/icon-strip' => 'service-strip',
        'content/feature-cards' => 'features',
        'social/feed' => 'social',
        'social/profiles' => 'social-profiles',
    ];

    /** The live contact page's Instagram feed shows ten; the grid takes at most twelve. */
    private const SOCIAL_FEED_MAX_POSTS = 12;

    private const CHECK_INS_MAX_ITEMS = 60;

    private const CHECK_IN_MAX_PHOTOS = 8;

    /** @var array<string, BlockDefinition>|null */
    private ?array $definitions = null;

    public function __construct(
        private readonly RichContent $richContent,
        private readonly BlockData $data,
        private readonly AreaRepository $areas,
        private readonly PublicLinkTarget $publicLinks,
    ) {}

    /**
     * @return array<string, BlockDefinition>
     */
    public function all(): array
    {
        return $this->definitions ??= $this->buildDefinitions();
    }

    public function definition(string $type): ?BlockDefinition
    {
        return $this->all()[$type] ?? null;
    }

    public function component(string $type): string
    {
        return $this->definition($type)?->component
            ?? throw new InvalidArgumentException("Unknown content block [{$type}].");
    }

    /**
     * Return the anchor the renderer will emit for this block, including its
     * component default when the payload omits an explicit id.
     *
     * @param  array<string, mixed>  $props
     */
    public function effectiveAnchor(string $type, array $props): ?string
    {
        if (! array_key_exists($type, self::DEFAULT_ANCHORS)) {
            return null;
        }

        return is_string($props['id'] ?? null) && trim($props['id']) !== ''
            ? trim($props['id'])
            : self::DEFAULT_ANCHORS[$type];
    }

    /**
     * Return every DOM id emitted by the selected renderer for collision
     * detection before publication.
     *
     * @param  array<string, mixed>  $props
     * @return array<int, string>
     */
    public function emittedIds(string $type, array $props): array
    {
        $ids = [];
        $anchor = $this->effectiveAnchor($type, $props);

        if ($anchor !== null) {
            $ids[] = $anchor;
        }

        if ($type === 'hero/split') {
            $ids[] = 'hero-title';
        } elseif ($type === 'content/split' && $this->filledString($props['heading'] ?? null)) {
            $ids[] = ($anchor ?? 'split').'-title';
        } elseif ($anchor !== null
            && ($this->filledString($props['heading'] ?? null)
                || $type === 'testimonials/carousel')) {
            $ids[] = $anchor.'-title';
        }

        if ($type === 'content/rich-text' && is_array($props['nodes'] ?? null)) {
            foreach ($props['nodes'] as $node) {
                if (is_array($node)
                    && ($node['type'] ?? null) === 'heading'
                    && $this->filledString($node['id'] ?? null)) {
                    $ids[] = trim($node['id']);
                }
            }
        }

        if (in_array($type, ['cta/quote-form', 'cta/quote-form-split'], true)
            && $anchor !== null) {
            $formId = $anchor.'-form';

            foreach (['name', 'email', 'phone', 'service', 'website', 'highlevel'] as $field) {
                $ids[] = $formId.'-'.$field;
            }

            if ($type === 'cta/quote-form'
                || ($props['showMessage'] ?? false) === true) {
                $ids[] = $formId.'-message';
            }
        }

        if ($type === 'content/benefit-carousel'
            && $anchor !== null
            && ! $this->filledString($props['heading'] ?? null)) {
            $ids[] = $anchor.'-title';
        }

        if ($type === 'photo-grid/gallery'
            && $anchor !== null
            && is_array($props['images'] ?? null)
            && array_is_list($props['images'])) {
            foreach (array_keys($props['images']) as $index) {
                $ids[] = $anchor.'-image-'.$index;
            }
        }

        // hero/photo renders the service icon strip under the header.
        if ($type === 'hero/photo' && ($props['serviceStrip'] ?? true) !== false) {
            $ids[] = 'service-strip';
        }

        return $ids;
    }

    /**
     * Resolve catalog-backed media into the exact renderer contract. Managed
     * revisions retain media_id references, while Blade components consume a
     * stable URL, alt text, and intrinsic dimensions.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function renderProps(string $type, array $props): array
    {
        foreach ([
            'content/split' => ['image', 'inset'],
            'content/story-checklist' => ['image', 'inset'],
            'cta/image-split' => ['image'],
            'cta/panel' => ['image', 'logo'],
            'cta/quote-form-split' => ['image', 'secondaryImage'],
            'cta/inline-band' => ['image'],
            'hero/split' => ['image'],
            'hero/service' => ['image'],
            'hero/photo' => ['image'],
            'hero/interior' => ['image'],
            'pricing/factors' => ['image'],
            'before-after/slider' => ['before', 'after'],
            'media/video' => ['poster'],
            'evidence/case-study' => ['image'],
            'evidence/local-project' => ['image'],
        ][$type] ?? [] as $field) {
            if (array_key_exists($field, $props) && $props[$field] !== null) {
                $props[$field] = $this->resolvedMedia($props[$field]);
            }
        }

        foreach ([
            'content/commitment-collage' => ['images'],
            'content/direct-answer' => ['media'],
            'content/rich-text' => ['media'],
            'photo-grid/gallery' => ['images'],
        ][$type] ?? [] as $field) {
            if (is_array($props[$field] ?? null)) {
                $props[$field] = collect($props[$field])
                    ->map(fn (mixed $media): ?array => $this->resolvedMedia($media))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        foreach ([
            'content/resource-cards' => ['items' => ['image']],
            'service-cards/grid' => ['services' => ['image']],
            'testimonials/carousel' => ['items' => ['avatar', 'poster']],
            'team/grid' => ['members' => ['image']],
            'logo-strip/credentials' => ['items' => ['image']],
            'pricing/monthly' => ['plans' => ['media']],
            'pricing/toggle' => ['plans' => ['media']],
            'pricing/yearly' => ['plans' => ['media']],
            'content/feature-cards' => ['items' => ['image']],
            'social/feed' => ['posts' => ['image']],
            'social/profiles' => ['profiles' => ['image']],
        ][$type] ?? [] as $collection => $fields) {
            if (! is_array($props[$collection] ?? null)) {
                continue;
            }

            $props[$collection] = collect($props[$collection])
                ->map(function (mixed $record) use ($fields): mixed {
                    if (! is_array($record)) {
                        return $record;
                    }

                    foreach ($fields as $field) {
                        if (array_key_exists($field, $record) && $record[$field] !== null) {
                            $record[$field] = $this->resolvedMedia($record[$field]);
                        }
                    }

                    return $record;
                })
                ->values()
                ->all();
        }

        if ($type === 'social/profiles'
            && is_array($props['card'] ?? null)
            && ($props['card']['image'] ?? null) !== null) {
            $props['card']['image'] = $this->resolvedMedia($props['card']['image']);
        }

        // Each check-in carries its own list of photos.
        if ($type === 'evidence/check-ins' && is_array($props['items'] ?? null)) {
            $props['items'] = collect($props['items'])
                ->map(function (mixed $item): mixed {
                    if (is_array($item) && is_array($item['photos'] ?? null)) {
                        $item['photos'] = collect($item['photos'])
                            ->map(fn (mixed $photo): ?array => $this->resolvedMedia($photo))
                            ->filter()
                            ->values()
                            ->all();
                    }

                    return $item;
                })
                ->values()
                ->all();
        }

        return $props;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvedMedia(mixed $media): ?array
    {
        $resolved = $this->data->media($media);

        if ($resolved === null || ! is_array($media)) {
            return $resolved;
        }

        if (in_array($media['fit'] ?? null, ['cover', 'contain'], true)) {
            $resolved['fit'] = $media['fit'];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function validate(
        string $type,
        array $props,
        array $unavailablePublicPaths = [],
    ): ContentValidationResult {
        $definition = $this->definition($type);

        if ($definition === null) {
            return new ContentValidationResult([
                '/type' => ['The selected block type is not allowed.'],
            ]);
        }

        $errors = [];
        $requiredErrors = [];

        foreach (array_diff(array_keys($props), array_keys($definition->fields)) as $field) {
            $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $field);
            $errors["/props/{$segment}"][] = 'This property is not part of the selected block contract.';
        }

        foreach ($definition->fields as $field => $schema) {
            $present = array_key_exists($field, $props)
                && $this->fieldIsPresent($props[$field], $schema);

            if (($schema['required'] ?? false) && ! $present) {
                $requiredErrors[] = "The {$field} field is required.";

                continue;
            }

            if (! $present || ! isset($schema['type'])) {
                continue;
            }

            $this->validateSchemaValue(
                $props[$field],
                $schema,
                "/props/{$field}",
                $errors,
            );
        }

        if ($requiredErrors !== []) {
            $errors = ['/props' => $requiredErrors] + $errors;
        }

        $result = new ContentValidationResult($errors);

        if ($type === 'content/rich-text' && array_key_exists('nodes', $props)) {
            $result = $result->merge(
                $this->richContent->validateNodes($props['nodes'], '/props/nodes'),
            );
        }

        if ($type === 'content/direct-answer' && array_key_exists('answer', $props)) {
            $result = $result->merge(
                $this->richContent->validateDirectAnswer($props['answer'], '/props/answer'),
            );
        }

        if ($type === 'content/direct-answer' && array_key_exists('supporting', $props)) {
            $result = $result->merge(
                $this->richContent->validateSegments(
                    $props['supporting'],
                    '/props/supporting',
                ),
            );
        }

        if ($type === 'announcement/bar'
            && array_key_exists('url', $props)
            && $this->fieldIsPresent($props['url'], ['type' => 'string'])
            && ! $this->richContent->isSafeUrl($props['url'])) {
            $result = $result->merge(new ContentValidationResult([
                '/props/url' => ['The announcement link URL is unsafe.'],
            ]));
        }

        return $result->merge(new ContentValidationResult(
            $this->validateStructuredProps(
                $type,
                $props,
                $unavailablePublicPaths,
            ),
        ));
    }

    /**
     * Renderer normalization is fail-closed. Validation must use the same
     * contract so accepted content cannot silently disappear at render time.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, array<int, string>>
     */
    private function validateStructuredProps(
        string $type,
        array $props,
        array $unavailablePublicPaths,
    ): array {
        $errors = [];

        foreach ([
            'content/resource-cards' => ['items'],
            'trust-bar/cards' => ['items'],
            'service-cards/grid' => ['services'],
            'testimonials/carousel' => ['items'],
            'testimonials/quotes' => ['items'],
            'content/commitment-collage' => ['images'],
            'content/direct-answer' => ['media'],
            'content/rich-text' => ['media'],
            'content/table-of-contents' => ['items'],
            'content/comparison-table' => ['columns', 'rows'],
            'pricing/factors' => ['factors'],
            'photo-grid/gallery' => ['images'],
            'team/grid' => ['members'],
            'stats/proof' => ['items'],
            'logo-strip/credentials' => ['items'],
            'service-areas/list' => ['areas'],
            'service-areas/grid' => ['areas'],
            'service-areas/coverage' => ['areas'],
            'content/related-content' => ['items'],
            'content/sources-reviewer' => ['sources'],
            'evidence/case-study' => ['facts'],
            'cta/visit-expectations' => ['items'],
            'content/feature-cards' => ['items'],
            'service-cards/icon-strip' => ['services'],
            'social/feed' => ['profiles', 'posts'],
            'social/profiles' => ['profiles'],
            'evidence/check-ins' => ['items'],
        ][$type] ?? [] as $field) {
            if (array_key_exists($field, $props)
                && (! is_array($props[$field]) || ! array_is_list($props[$field]))) {
                $errors["/props/{$field}"][] = 'The collection must be an ordered array.';
            }
        }

        foreach ([
            'content/capability-strip' => ['items'],
            'content/story-checklist' => ['items'],
            'content/inclusions-exclusions' => ['included', 'excluded'],
            'cta/form-confirmation' => ['steps'],
        ][$type] ?? [] as $field) {
            if (array_key_exists($field, $props)) {
                $this->validateStringList($props[$field], "/props/{$field}", $errors);
            }
        }

        if ($type === 'content/split' && array_key_exists('checklist', $props)) {
            $this->validateChecklist($props['checklist'], '/props/checklist', $errors);
        }

        if (in_array($type, ['faq/accordion', 'faq/highlighted'], true)
            && array_key_exists('items', $props)) {
            $this->validateRecords(
                $props['items'],
                '/props/items',
                ['question', 'answer'],
                $errors,
            );
        }

        if ($type === 'process-steps/grid' && array_key_exists('steps', $props)) {
            $this->validateRecords(
                $props['steps'],
                '/props/steps',
                ['number', 'title', 'body'],
                $errors,
            );
        }

        if ($type === 'process-steps/icon-grid' && array_key_exists('steps', $props)) {
            $this->validateRecords(
                $props['steps'],
                '/props/steps',
                ['icon', 'title', 'body'],
                $errors,
            );
        }

        if (in_array($type, ['pricing/monthly', 'pricing/toggle', 'pricing/yearly'], true)) {
            $this->validatePricingPlans($type, $props['plans'] ?? null, $errors);
        }

        if ($type === 'content/resource-cards' && array_key_exists('items', $props)) {
            $this->validateRecords(
                $props['items'],
                '/props/items',
                ['title', 'body'],
                $errors,
                function (array $item, string $pointer, array &$errors): void {
                    if (array_key_exists('href', $item)
                        && ! $this->richContent->isSafeUrl($item['href'])) {
                        $errors["{$pointer}/href"][] = 'The optional resource link must be safe.';
                    }

                    if (array_key_exists('image', $item)
                        && $item['image'] !== null
                        && $this->data->media($item['image']) === null) {
                        $errors[$pointer][] = 'The optional resource image needs valid media metadata.';
                    }
                },
            );
        }

        if ($type === 'trust-bar/cards') {
            $this->validateRecords(
                $props['items'] ?? null,
                '/props/items',
                ['title', 'body'],
                $errors,
            );
        }

        if ($type === 'service-cards/grid' && array_key_exists('services', $props)) {
            $this->validateRecords(
                $props['services'],
                '/props/services',
                ['title', 'body'],
                $errors,
                function (array $item, string $pointer, array &$errors): void {
                    if (array_key_exists('href', $item)
                        && ! $this->richContent->isSafeUrl($item['href'])) {
                        $errors[$pointer][] = 'The optional service link must be safe.';
                    }

                    if (array_key_exists('image', $item)
                        && $item['image'] !== null
                        && $this->data->media($item['image']) === null) {
                        $errors[$pointer][] = 'The optional service image needs valid media metadata.';
                    }
                },
            );
        }

        if ($type === 'testimonials/quotes' && array_key_exists('items', $props)) {
            $this->validateRecords(
                $props['items'],
                '/props/items',
                ['quote', 'author'],
                $errors,
                function (array $item, string $pointer, array &$errors): void {
                    if (array_key_exists('rating', $item)
                        && (! is_int($item['rating']) || $item['rating'] < 1 || $item['rating'] > 5)) {
                        $errors[$pointer][] = 'A testimonial rating must be an integer from 1 to 5.';
                    }
                },
            );
        }

        if ($type === 'testimonials/carousel' && array_key_exists('items', $props)) {
            foreach ($this->listItems($props['items']) as $index => $item) {
                $pointer = "/props/items/{$index}";

                if (! is_array($item)) {
                    $errors[$pointer][] = 'Each testimonial must be a record.';

                    continue;
                }

                $itemType = $item['type'] ?? 'quote';

                if (! in_array($itemType, ['quote', 'video'], true)) {
                    $errors["{$pointer}/type"][] = 'The testimonial type must be quote or video.';

                    continue;
                }

                if ($itemType === 'video') {
                    if (! $this->filledString($item['title'] ?? null)) {
                        $errors["{$pointer}/title"][] = 'A video testimonial needs a title.';
                    }
                    if (! $this->richContent->isSafeMediaUrl($item['video_url'] ?? null)) {
                        $errors["{$pointer}/video_url"][] = 'A video testimonial needs a safe video URL.';
                    }
                    if (! $this->filledString($item['transcript'] ?? null)) {
                        $errors["{$pointer}/transcript"][] = 'A video testimonial needs a transcript.';
                    }
                    if ($this->data->media($item['poster'] ?? null) === null) {
                        $errors["{$pointer}/poster"][] = 'A video testimonial needs a valid poster.';
                    }
                } else {
                    foreach (['quote', 'author'] as $field) {
                        if (! $this->filledString($item[$field] ?? null)) {
                            $errors["{$pointer}/{$field}"][] = "A quote testimonial needs a {$field}.";
                        }
                    }
                }

                if (array_key_exists('avatar', $item)
                    && $item['avatar'] !== null
                    && $this->data->media($item['avatar']) === null) {
                    $errors["{$pointer}/avatar"][] = 'The testimonial avatar needs valid media metadata.';
                }
            }
        }

        if ($type === 'content/commitment-collage') {
            if (array_key_exists('points', $props)) {
                $this->validateStringList($props['points'], '/props/points', $errors);
            }
            if (array_key_exists('images', $props)) {
                $this->validateMediaList($props['images'], '/props/images', $errors);
            }
            if (array_key_exists('cta', $props) && $this->data->link($props['cta']) === null) {
                $errors['/props/cta'][] = 'The call to action needs a label and safe link.';
            }
        }

        foreach ([
            'cta/image-split' => 'cta',
            'cta/sticky-mobile' => 'primaryAction',
            'cta/inline-band' => 'primaryAction',
            'hero/service' => 'primaryAction',
            'hero/location' => 'primaryAction',
        ] as $blockType => $field) {
            if ($type === $blockType && $this->data->link($props[$field] ?? null) === null) {
                $errors["/props/{$field}"][] = 'The action needs a label and safe link.';
            }
        }

        foreach ([
            'content/direct-answer' => ['source', 'nextStep'],
            'cta/inline-band' => ['secondaryAction'],
            // Optional: the live guarantee bands are statements with no button.
            'cta/panel' => ['cta'],
            'hero/interior' => ['primaryAction', 'secondaryAction'],
            'hero/location' => ['secondaryAction'],
            'hero/service' => ['secondaryAction'],
            'hero/split' => ['cta', 'secondary'],
        ][$type] ?? [] as $field) {
            if (array_key_exists($field, $props)
                && $props[$field] !== null
                && $this->data->link($props[$field]) === null) {
                $errors["/props/{$field}"][] = 'The optional action needs a label and safe link.';
            }
        }

        foreach ([
            'content/split' => ['image', 'inset'],
            'content/story-checklist' => ['image', 'inset'],
            'cta/image-split' => ['image'],
            'cta/panel' => ['image', 'logo'],
            'cta/quote-form-split' => ['image', 'secondaryImage'],
            'cta/inline-band' => ['image'],
            'hero/split' => ['image'],
            'hero/service' => ['image'],
        ][$type] ?? [] as $field) {
            if (array_key_exists($field, $props)
                && $props[$field] !== null
                && $this->data->media($props[$field]) === null) {
                $errors["/props/{$field}"][] = 'The optional image needs valid media metadata.';
            }
        }

        if ($type === 'content/table-of-contents') {
            foreach ($this->listItems($props['items'] ?? null) as $index => $item) {
                if (! is_array($item)
                    || ! $this->filledString($item['label'] ?? null)
                    || $this->data->anchor($item['href'] ?? null) === null) {
                    $errors["/props/items/{$index}"][] = 'Each table-of-contents item needs a label and valid page anchor.';
                }
            }
        }

        if ($type === 'pricing/factors') {
            $this->validateRecords(
                $props['factors'] ?? null,
                '/props/factors',
                ['label', 'description'],
                $errors,
            );
        }

        if ($type === 'content/comparison-table') {
            $this->validateComparison($props, $errors);
        }

        if ($type === 'photo-grid/gallery') {
            $this->validateMediaList($props['images'] ?? null, '/props/images', $errors);
        }

        if (in_array($type, ['content/direct-answer', 'content/rich-text'], true)
            && array_key_exists('media', $props)) {
            $this->validateMediaList($props['media'], '/props/media', $errors);
        }

        if ($type === 'before-after/slider') {
            foreach (['before', 'after'] as $field) {
                if ($this->data->media($props[$field] ?? null, true) === null) {
                    $errors["/props/{$field}"][] = "The {$field} image needs safe media metadata and a source.";
                }
            }
        }

        if (in_array($type, ['media/video', 'hero/split'], true)
            && array_key_exists('video', $props)
            && $props['video'] !== null) {
            $video = $props['video'] ?? null;

            if (! is_array($video)
                || ! $this->richContent->isSafeMediaUrl($video['src'] ?? null)
                || ! in_array($video['type'] ?? null, ['video/mp4', 'video/webm', 'video/ogg'], true)) {
                $errors['/props/video'][] = 'The video needs a safe source and an allowed video MIME type.';
            }

            if ($type === 'media/video'
                && $this->data->media($props['poster'] ?? null) === null) {
                $errors['/props/poster'][] = 'The poster needs a safe source, alt text, width, and height.';
            }
        }

        if ($type === 'hero/article'
            && $this->data->date($props['publishedAt'] ?? null) === null) {
            $errors['/props/publishedAt'][] = 'The publication date must use YYYY-MM-DD.';
        }

        /*
         * Optional, because most articles are never revised. When an article is
         * substantially rewritten the original publication date on its own
         * describes it as untouched since, which is the opposite of what
         * happened.
         */
        if ($type === 'hero/article'
            && ($props['updatedAt'] ?? null) !== null) {
            if ($this->data->date($props['updatedAt']) === null) {
                $errors['/props/updatedAt'][] = 'The updated date must use YYYY-MM-DD.';
            } elseif ($this->data->date($props['publishedAt'] ?? null) !== null
                && $props['updatedAt'] < $props['publishedAt']) {
                // Both are YYYY-MM-DD by this point, so a string compare orders
                // them correctly. dateModified before datePublished is markup
                // that contradicts itself.
                $errors['/props/updatedAt'][] = 'The updated date cannot precede the publication date.';
            }

            /*
             * A reviewer endorses a version, not a URL. Left unchecked, a
             * substantive rewrite dated after the review still renders
             * "Reviewed by …" beside the earlier date, so the current text
             * carries an accountability signal nobody gave it — worst exactly
             * where a reviewer matters most, on held health and safety copy.
             * Re-review the revision, or drop the reviewer when creating it.
             */
            $reviewedAt = is_array($props['reviewer'] ?? null)
                ? ($props['reviewer']['reviewed_at'] ?? null)
                : null;

            if ($this->data->date($props['updatedAt']) !== null
                && $this->data->date($reviewedAt) !== null
                && $reviewedAt < $props['updatedAt']) {
                $errors['/props/reviewer/reviewed_at'][] = 'The review predates this revision, so it cannot vouch for it. Re-review it or remove the reviewer.';
            }
        }

        if ($type === 'team/grid') {
            foreach ($this->listItems($props['members'] ?? null) as $index => $member) {
                if (! is_array($member)
                    || ! $this->filledString($member['name'] ?? null)
                    || ! $this->filledString($member['role'] ?? null)
                    || $this->data->media($member['image'] ?? null) === null
                    || (isset($member['linkedin_url'])
                        && ! $this->richContent->isSafeUrl($member['linkedin_url']))) {
                    $errors["/props/members/{$index}"][] = 'Each team member needs a name, role, valid image, and safe optional profile URL.';
                }
            }
        }

        if ($type === 'stats/proof') {
            foreach ($this->listItems($props['items'] ?? null) as $index => $item) {
                if (! is_array($item)
                    || ! $this->filledString($item['value'] ?? null)
                    || ! $this->filledString($item['label'] ?? null)
                    || $this->data->source($item) === null) {
                    $errors["/props/items/{$index}"][] = 'Each statistic needs a value, label, and valid source.';
                }
            }
        }

        if ($type === 'logo-strip/credentials') {
            foreach ($this->listItems($props['items'] ?? null) as $index => $item) {
                if (! is_array($item)
                    || ! $this->filledString($item['name'] ?? null)
                    || $this->data->source($item) === null
                    || $this->data->media($item['image'] ?? null) === null) {
                    $errors["/props/items/{$index}"][] = 'Each credential needs a name, valid image, and source.';
                }
            }
        }

        if (in_array($type, ['service-areas/list', 'service-areas/grid', 'service-areas/coverage'], true)) {
            $areas = $this->listItems($props['areas'] ?? null);

            foreach ($areas as $index => $area) {
                if ($this->data->serviceAreas([$area]) === []
                    || ! $this->governedArea($area)) {
                    $errors["/props/areas/{$index}"][] = 'Each service area needs a place name and safe link.';
                }
            }
        }

        if ($type === 'hero/location'
            && ! $this->governedArea([
                'name' => $props['place'] ?? null,
            ], checkHref: false)) {
            $errors['/props/place'][] = 'The location hero place must match a configured service area.';
        }

        if ($type === 'content/related-content') {
            foreach ($this->listItems($props['items'] ?? null) as $index => $item) {
                if (! is_array($item)
                    || ! $this->filledString($item['title'] ?? null)
                    || $this->data->link([
                        'label' => $item['title'] ?? null,
                        'href' => $item['href'] ?? null,
                    ]) === null
                    || ! $this->publicLinks->exists(
                        $item['href'] ?? null,
                        $unavailablePublicPaths,
                    )) {
                    $errors["/props/items/{$index}"][] = 'Each related item needs a title and safe link.';
                }
            }
        }

        if ($type === 'content/sources-reviewer') {
            foreach ($this->listItems($props['sources'] ?? null) as $index => $source) {
                if (! is_array($source)
                    || ! $this->filledString($source['label'] ?? null)
                    || $this->data->date($source['reviewed_at'] ?? null) === null
                    || $this->data->link([
                        'label' => $source['label'] ?? null,
                        'href' => $source['url'] ?? null,
                    ]) === null) {
                    $errors["/props/sources/{$index}"][] = 'Each source needs a label, safe URL, and valid review date.';
                }
            }
        }

        if (in_array($type, ['content/sources-reviewer', 'hero/article'], true)
            && array_key_exists('reviewer', $props)
            && $props['reviewer'] !== null) {
            $reviewer = $props['reviewer'];

            if (! is_array($reviewer)) {
                $errors['/props/reviewer'][] = 'The reviewer must be a record.';
            } else {
                if (! $this->filledString($reviewer['name'] ?? null)) {
                    $errors['/props/reviewer/name'][] = 'The reviewer needs a name.';
                }
                if ($type === 'content/sources-reviewer'
                    && ! $this->filledString($reviewer['role'] ?? null)) {
                    $errors['/props/reviewer/role'][] = 'The reviewer needs a role.';
                }
                if ($this->data->date($reviewer['reviewed_at'] ?? null) === null) {
                    $errors['/props/reviewer/reviewed_at'][] = 'The review date must use YYYY-MM-DD.';
                }
            }
        }

        if ($type === 'evidence/case-study') {
            if (array_key_exists('image', $props)
                && $props['image'] !== null
                && $this->data->media($props['image']) === null) {
                $errors['/props/image'][] = 'The case-study image needs safe media metadata.';
            }

            foreach ($this->listItems($props['facts'] ?? []) as $index => $fact) {
                if (! is_array($fact)
                    || ! $this->filledString($fact['label'] ?? null)
                    || ! $this->filledString($fact['value'] ?? null)
                    || $this->data->source($fact) === null) {
                    $errors["/props/facts/{$index}"][] = 'Each fact needs a label, value, and valid source.';
                }
            }
        }

        if ($type === 'evidence/local-project') {
            if ($this->data->serviceAreaName($props['place'] ?? null) === null
                || ! $this->governedArea([
                    'name' => $props['place'] ?? null,
                    'href' => $props['source']['href'] ?? null,
                ], checkHref: false)) {
                $errors['/props/place'][] = 'The project place must be a service-area name, not an address.';
            }

            if ($this->data->link($props['source'] ?? null) === null) {
                $errors['/props/source'][] = 'The local project needs a valid source link.';
            }

            if (array_key_exists('image', $props)
                && $props['image'] !== null
                && $this->data->media($props['image']) === null) {
                $errors['/props/image'][] = 'The project image needs safe media metadata.';
            }
        }

        if ($type === 'cta/visit-expectations') {
            foreach ($this->listItems($props['items'] ?? null) as $index => $item) {
                if (! is_array($item)
                    || ! $this->filledString($item['label'] ?? null)
                    || ! $this->filledString($item['description'] ?? null)) {
                    $errors["/props/items/{$index}"][] = 'Each expectation needs a label and description.';
                }
            }
        }

        $this->validatePhotoHeroIconStripAndFeatureCards($type, $props, $errors);
        $this->validateCopperEagleSectionOptions($type, $props, $errors);
        $this->validateSocialBlocks($type, $props, $errors);
        $this->validateCheckIns($type, $props, $errors);

        if (in_array($type, ['hero/photo', 'hero/interior'], true)
            && array_key_exists('video', $props)
            && $props['video'] !== null
            && $this->data->backgroundVideo($props['video']) === null) {
            $errors['/props/video'][] = 'The background video needs a safe .mp4 or .webm source and a poster with a safe source, width, and height.';
        }

        return $errors;
    }

    /**
     * evidence/check-ins. Each check-in names who did the work and the day,
     * and shows a write-up, photos, or both. Every photo needs alt text and
     * its real size.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateCheckIns(string $type, array $props, array &$errors): void
    {
        if ($type !== 'evidence/check-ins') {
            return;
        }

        $items = $this->listItems($props['items'] ?? []);

        if (count($items) > self::CHECK_INS_MAX_ITEMS) {
            $errors['/props/items'][] = 'The section shows at most '.self::CHECK_INS_MAX_ITEMS.' check-ins.';
        }

        foreach ($items as $index => $item) {
            $pointer = "/props/items/{$index}";

            if (! is_array($item)) {
                $errors[$pointer][] = 'Each check-in must be a record.';

                continue;
            }

            if (! $this->filledString($item['author'] ?? null)) {
                $errors[$pointer][] = 'Each check-in needs the name of whoever did the work.';
            }

            $date = is_string($item['date'] ?? null)
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $item['date'])
                : false;

            if ($date === false || $date->format('Y-m-d') !== $item['date']) {
                $errors[$pointer][] = 'Each check-in needs a real date written as YYYY-MM-DD.';
            }

            $photos = $item['photos'] ?? [];

            if (! is_array($photos) || ! array_is_list($photos)) {
                $errors[$pointer][] = 'The photos must be an ordered array.';

                continue;
            }

            if (count($photos) > self::CHECK_IN_MAX_PHOTOS) {
                $errors[$pointer][] = 'A check-in shows at most '.self::CHECK_IN_MAX_PHOTOS.' photos.';
            }

            foreach ($photos as $photo) {
                if ($this->data->media($photo) === null) {
                    $errors[$pointer][] = 'Each photo needs a safe source, alt text, width, and height.';

                    break;
                }
            }

            if ($photos === [] && ! $this->filledString($item['body'] ?? null)) {
                $errors[$pointer][] = 'A check-in needs a write-up, photos, or both.';
            }
        }
    }

    /**
     * social/feed and social/profiles. Profile and post links open in a new
     * tab, so they must be absolute http(s) addresses on another site.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateSocialBlocks(string $type, array $props, array &$errors): void
    {
        if ($type === 'social/feed') {
            foreach ($this->listItems($props['profiles'] ?? []) as $index => $profile) {
                if (! is_array($profile)
                    || ! $this->filledString($profile['label'] ?? null)
                    || ! $this->filledString($profile['handle'] ?? null)
                    || (($profile['href'] ?? null) !== null && $this->data->externalUrl($profile['href']) === null)) {
                    $errors["/props/profiles/{$index}"][] = 'Each profile needs a network label, a handle, and an optional absolute http(s) link.';
                }
            }

            $posts = $this->listItems($props['posts'] ?? []);

            if (count($posts) > self::SOCIAL_FEED_MAX_POSTS) {
                $errors['/props/posts'][] = 'The feed shows at most '.self::SOCIAL_FEED_MAX_POSTS.' posts.';
            }

            foreach ($posts as $index => $post) {
                if (! is_array($post)
                    || $this->data->externalUrl($post['href'] ?? null) === null
                    || $this->data->media($post['image'] ?? null) === null) {
                    $errors["/props/posts/{$index}"][] = 'Each post needs an absolute http(s) link and an image with alt text, width, and height.';
                }
            }
        }

        if ($type === 'social/profiles') {
            foreach ($this->listItems($props['profiles'] ?? []) as $index => $profile) {
                if (! is_array($profile)
                    || $this->data->externalUrl($profile['href'] ?? null) === null
                    || $this->data->media($profile['image'] ?? null) === null) {
                    $errors["/props/profiles/{$index}"][] = 'Each profile tile needs an absolute http(s) link and an image whose alt text names the profile.';
                }
            }

            if (array_key_exists('card', $props) && $props['card'] !== null) {
                $card = $props['card'];

                if (! is_array($card)
                    || $this->data->link($card) === null
                    || $this->data->media($card['image'] ?? null) === null) {
                    $errors['/props/card'][] = 'The contact card needs a label, a safe link, and an image with alt text, width, and height.';
                }
            }
        }
    }

    /**
     * The optional presentation props on the restyled shared blocks
     * (hero/interior, content/split, testimonials/quotes, service-areas/list,
     * cta/inline-band, cta/panel, pricing/factors). Enumerated values are
     * enforced by their field schemas; this covers what a schema cannot.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateCopperEagleSectionOptions(
        string $type,
        array $props,
        array &$errors,
    ): void {
        // <x-content.emphasized> renders plain text for anything else, so an
        // emphasis that is not in the heading would silently disappear.
        if (in_array($type, ['content/split', 'cta/inline-band'], true)
            && $this->filledString($props['emphasis'] ?? null)
            && is_string($props['heading'] ?? null)
            && ! str_contains($props['heading'], trim($props['emphasis']))) {
            $errors['/props/emphasis'][] = 'The emphasis must be an exact phrase from the heading.';
        }

        foreach ([
            'content/split' => 'cta',
            'testimonials/quotes' => 'link',
        ] as $blockType => $field) {
            if ($type === $blockType
                && array_key_exists($field, $props)
                && $props[$field] !== null
                && $this->data->link($props[$field]) === null) {
                $errors["/props/{$field}"][] = 'The optional action needs a label and safe link.';
            }
        }

        if (in_array($type, ['hero/interior', 'pricing/factors'], true)
            && array_key_exists('image', $props)
            && $props['image'] !== null
            && $this->data->media($props['image']) === null) {
            $errors['/props/image'][] = 'The optional image needs valid media metadata.';
        }
    }

    /**
     * Schemas for the optional presentation props of the restyled shared
     * blocks, passed to define() as overrides. Every one is optional and
     * defaults to the block's original look.
     *
     * @return array<string, array<string, mixed>>
     */
    private function copperEagleSectionFields(string $type): array
    {
        $link = fn (string $description): array => [
            ...$this->field('array', false, $description),
            'shape' => 'object',
            'properties' => [
                'label' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', true),
            ],
        ];
        $image = fn (string $description): array => [
            ...$this->field('array', false, $description),
            'shape' => 'object',
            'properties' => $this->mediaProperties(),
        ];

        return match ($type) {
            'hero/interior' => [
                'image' => $image('Optional photo behind the deep-blue band, darkened and decorative. Without it the band is plain deep blue.'),
                'video' => $this->backgroundVideoField('Optional decorative background video behind the deep-blue band, over the photo when there is one. Muted and looping; a reduced-motion visitor sees the poster.'),
            ],
            'content/split' => [
                'emphasis' => $this->field('string', false, 'Optional exact phrase from the heading, rendered in the heavier weight.'),
                'lead' => $this->field('string', false, 'Optional bold lead sentence before the body.'),
                'cta' => $link('Optional copper button after the copy.'),
                'imageShape' => $this->field('string', false, 'Photo shape. Defaults to rectangle (square crop); circle is the portrait, with the inset as a badge over its lower-left edge.', ['rectangle', 'circle']),
                'imageSide' => $this->field('string', false, 'Which column holds the photo. Defaults to start.', ['start', 'end']),
                'background' => $this->field('string', false, 'Full-width band behind the section. Defaults to white.', ['white', 'mist', 'stone']),
            ],
            'testimonials/quotes' => [
                'link' => $link('Optional outline button under the quotes, such as a link to every testimonial.'),
            ],
            'service-areas/list' => [
                'note' => $this->field('string', false, 'Optional small uppercase note under the area names.'),
            ],
            'cta/inline-band' => [
                'emphasis' => $this->field('string', false, 'Optional exact phrase from the heading, rendered underlined.'),
                'background' => $this->field('string', false, 'Band behind the prompt. Defaults to white.', ['white', 'mist']),
                'buttonStyle' => $this->field('string', false, 'Primary button look. Defaults to copper.', ['copper', 'sky']),
                'image' => $image('Optional photo on the start side of the prompt, such as a portrait. Without it the prompt is centered on its own.'),
            ],
            'cta/panel' => [
                'body' => $this->field('string', false, 'Optional text under the heading. A blank line starts a new paragraph.'),
                'cta' => $link('Optional button. Without it the band is a statement, such as the guarantee, and offers no call line either.'),
                'tone' => $this->field('string', false, 'Deep-blue band (primary, the default) or the solid copper guarantee band. The copper band ignores image.', ['primary', 'copper']),
                'image' => $image('Optional photo behind the deep-blue band, darkened and decorative.'),
                'logo' => $image('Optional small logo above the heading, such as the review site\'s mark. Its alt text names it.'),
            ],
            'cta/quote-form-split' => [
                'showContact' => $this->field('boolean', false, 'Whether the phone number (as a call button under the heading) and the email address (under the body) from site settings are shown. Defaults to false.'),
                'secondaryImage' => $image('Optional second photo stacked under the first in the photo column.'),
            ],
            'pricing/factors' => [
                'body' => $this->field('string', false, 'Optional short introduction under the heading.'),
                'image' => $image('Optional photo behind the deep-blue band, darkened and decorative.'),
            ],
            default => [],
        };
    }

    /**
     * hero/photo, service-cards/icon-strip, and content/feature-cards.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $errors
     */
    private function validatePhotoHeroIconStripAndFeatureCards(
        string $type,
        array $props,
        array &$errors,
    ): void {
        // <x-content.emphasized> renders plain text for anything else, so an
        // emphasis that is not in the heading would silently disappear.
        if (in_array($type, ['hero/photo', 'content/feature-cards'], true)
            && $this->filledString($props['emphasis'] ?? null)
            && is_string($props['heading'] ?? null)
            && ! str_contains($props['heading'], trim($props['emphasis']))) {
            $errors['/props/emphasis'][] = 'The emphasis must be an exact phrase from the heading.';
        }

        if ($type === 'hero/photo') {
            if ($this->data->link($props['primaryAction'] ?? null) === null) {
                $errors['/props/primaryAction'][] = 'The action needs a label and safe link.';
            }

            if (array_key_exists('image', $props)
                && $props['image'] !== null
                && $this->data->media($props['image']) === null) {
                $errors['/props/image'][] = 'The hero image needs a safe source, alt text, width, and height.';
            }
        }

        if ($type === 'content/feature-cards' && array_key_exists('items', $props)) {
            $count = count($this->listItems($props['items']));

            if (is_array($props['items']) && array_is_list($props['items'])
                && ($count < 2 || $count > 6)) {
                $errors['/props/items'][] = 'Feature cards need two to six items.';
            }

            $this->validateRecords(
                $props['items'],
                '/props/items',
                ['title', 'body'],
                $errors,
                function (array $item, string $pointer, array &$errors): void {
                    if (array_key_exists('image', $item)
                        && $item['image'] !== null
                        && $this->data->media($item['image']) === null) {
                        $errors["{$pointer}/image"][] = 'Each card image needs a safe source, alt text, width, and height.';
                    }
                },
            );
        }

        if ($type === 'service-cards/icon-strip'
            && is_array($props['services'] ?? null)
            && array_is_list($props['services'])) {
            $services = app(ServiceRepository::class);

            foreach ($props['services'] as $index => $slug) {
                $service = is_string($slug) ? $services->find(trim($slug)) : null;

                if (! $service instanceof Service || $service->icon() === null) {
                    $errors["/props/services/{$index}"][] = 'Each service must be the slug of a service record with a valid icon.';
                }
            }
        }
    }

    private function governedArea(mixed $candidate, bool $checkHref = true): bool
    {
        if (! is_array($candidate) || ! is_string($candidate['name'] ?? null)) {
            return false;
        }

        // An area may be named on its own ("Murray") or with its region
        // ("Murray, Utah"), the way service-area lists usually write it.
        $area = $this->areas->all()->first(
            fn (Area $area): bool => strcasecmp($area->name(), trim($candidate['name'])) === 0
                || strcasecmp($area->label(), trim($candidate['name'])) === 0,
        );

        if (! $checkHref) {
            return $area instanceof Area;
        }

        if (! $area instanceof Area) {
            return false;
        }

        /*
         * Compared against the record's own URL rather than a rebuilt
         * `/areas/{slug}`, so an area the intent ledger has folded is required
         * to link at its destination instead of through the redirect.
         */
        return parse_url((string) ($candidate['href'] ?? ''), PHP_URL_PATH)
            === parse_url($area->url(), PHP_URL_PATH);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validatePricingPlans(
        string $type,
        mixed $plans,
        array &$errors,
    ): void {
        $modes = match ($type) {
            'pricing/monthly' => ['monthly'],
            'pricing/yearly' => ['yearly'],
            default => ['monthly', 'yearly'],
        };

        if (! is_array($plans) || ! array_is_list($plans)) {
            $errors['/props/plans'][] = 'Pricing plans must be an ordered array.';

            return;
        }

        foreach ($plans as $index => $plan) {
            $pointer = "/props/plans/{$index}";

            if (! is_array($plan) || ! $this->filledString($plan['name'] ?? null)) {
                $errors[$pointer][] = 'Each pricing plan needs a name.';

                continue;
            }

            foreach ($modes as $mode) {
                $amount = $plan['prices'][$mode]['amount'] ?? null;

                if ((! is_string($amount) && ! is_int($amount) && ! is_float($amount))
                    || trim((string) $amount) === '') {
                    $errors["{$pointer}/prices/{$mode}"][] = "Each pricing plan needs a {$mode} amount.";
                }
            }

            if (array_key_exists('features', $plan)) {
                $this->validateStringList($plan['features'], "{$pointer}/features", $errors);
            }

            if (array_key_exists('cta', $plan)
                && $plan['cta'] !== null
                && $this->data->link($plan['cta']) === null) {
                $errors["{$pointer}/cta"][] = 'The pricing action needs a label and safe link.';
            }

            if (array_key_exists('media', $plan)
                && $plan['media'] !== null
                && $this->data->media($plan['media']) === null) {
                $errors["{$pointer}/media"][] = 'The pricing image needs valid media metadata.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateComparison(array $props, array &$errors): void
    {
        $columns = $this->listItems($props['columns'] ?? null);
        $keys = [];

        foreach ($columns as $index => $column) {
            if (! is_array($column)
                || ! is_string($column['key'] ?? null)
                || preg_match('/^[a-z][a-z0-9_]*$/', $column['key']) !== 1
                || ! $this->filledString($column['label'] ?? null)) {
                $errors["/props/columns/{$index}"][] = 'Each comparison column needs a stable key and label.';
            } elseif (in_array($column['key'], $keys, true)) {
                $errors["/props/columns/{$index}/key"][] = 'Comparison column keys must be unique.';
            } else {
                $keys[] = $column['key'];
            }
        }

        foreach ($this->listItems($props['rows'] ?? null) as $index => $row) {
            if (! is_array($row)
                || ! $this->filledString($row['label'] ?? null)
                || ! is_array($row['values'] ?? null)) {
                $errors["/props/rows/{$index}"][] = 'Each comparison row needs a label and values object.';

                continue;
            }

            foreach ($keys as $key) {
                if (! is_string($row['values'][$key] ?? null)) {
                    $errors["/props/rows/{$index}/values/{$key}"][] = 'Each comparison cell must be a string.';
                }
            }

            foreach (array_diff(array_keys($row['values']), $keys) as $key) {
                $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
                $errors["/props/rows/{$index}/values/{$segment}"][] = 'The comparison cell has no matching column.';
            }
        }
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<string, array<int, string>>  $errors
     * @param  (callable(array<string, mixed>, string, array<string, array<int, string>>&): void)|null  $extra
     */
    private function validateRecords(
        mixed $items,
        string $pointer,
        array $required,
        array &$errors,
        ?callable $extra = null,
    ): void {
        if (! is_array($items) || ! array_is_list($items)) {
            $errors[$pointer][] = 'This field must be an ordered array.';

            return;
        }

        foreach ($items as $index => $item) {
            $itemPointer = "{$pointer}/{$index}";

            if (! is_array($item)) {
                $errors[$itemPointer][] = 'Each item must be a record.';

                continue;
            }

            foreach ($required as $field) {
                if (! $this->filledString($item[$field] ?? null)) {
                    $errors[$itemPointer][] = "Each item needs a {$field}.";
                }
            }

            $extra?->__invoke($item, $itemPointer, $errors);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateStringList(
        mixed $items,
        string $pointer,
        array &$errors,
    ): void {
        if (! is_array($items)
            || ! array_is_list($items)
            || count($this->data->strings($items)) !== count($items)) {
            $errors[$pointer][] = 'Every item must be a non-empty string.';
        }
    }

    /**
     * A content/split checklist: each point is a non-empty string, or a
     * record with a non-empty label and text. The field schema already
     * rejects unknown keys and blank label or text values.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateChecklist(
        mixed $items,
        string $pointer,
        array &$errors,
    ): void {
        if (! is_array($items) || ! array_is_list($items)) {
            $errors[$pointer][] = 'The checklist must be an ordered array.';

            return;
        }

        foreach ($items as $index => $item) {
            $valid = is_string($item)
                ? trim($item) !== ''
                : is_array($item) && $item !== [] && ! array_is_list($item)
                    && $this->filledString($item['label'] ?? null)
                    && $this->filledString($item['text'] ?? null);

            if (! $valid) {
                $errors["{$pointer}/{$index}"][] = 'Each point must be a non-empty string, or a label and text.';
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateMediaList(mixed $items, string $pointer, array &$errors): void
    {
        foreach ($this->listItems($items) as $index => $item) {
            if ($this->data->media($item) === null) {
                $errors["{$pointer}/{$index}"][] = 'Each image needs a safe source, alt text, width, and height.';
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function listItems(mixed $items): array
    {
        return is_array($items) && array_is_list($items) ? $items : [];
    }

    private function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function fieldIsPresent(mixed $value, array $schema): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }

        return ($schema['type'] ?? null) !== 'string'
            || ! is_string($value)
            || trim($value) !== '';
    }

    /**
     * @return array<string, BlockDefinition>
     */
    private function buildDefinitions(): array
    {
        $definitions = [
            $this->define('announcement/bar', 'Display a time-bound site announcement.', ['message']),
            $this->define('content/benefit-carousel', 'Present the maintained set of service benefits.'),
            $this->define('content/capability-strip', 'Summarize core capabilities in a compact row.', ['items']),
            $this->define('content/commitment-collage', 'Pair company commitments with approved imagery.', ['heading']),
            $this->define('content/resource-cards', 'Link readers to selected educational resources.', ['heading', 'items']),
            $this->define('content/split', 'Pair explanatory copy with one approved image.', ['heading', 'body'], $this->copperEagleSectionFields('content/split')),
            $this->define('content/story-checklist', 'Tell a company story beside a checklist.', ['heading', 'items']),
            $this->define('cta/image-split', 'Present a primary response beside approved imagery.', ['heading', 'cta']),
            $this->define('cta/panel', 'Present one focused response, or a statement band with no button.', ['heading'], $this->copperEagleSectionFields('cta/panel')),
            $this->define('cta/quote-form-split', 'Pair the quote form with an approved image.', ['heading'], $this->copperEagleSectionFields('cta/quote-form-split')),
            $this->define('cta/quote-form', 'Render the shared quote form.', ['heading']),
            $this->define('faq/accordion', 'Answer a crawlable collection of common questions.', ['heading', 'items']),
            $this->define('faq/highlighted', 'Highlight a compact set of common questions.', ['heading', 'items']),
            $this->define('hero/split', 'Render the existing split marketing hero.', ['heading']),
            $this->define('pricing/monthly', 'Render monthly pricing cards.', ['plans']),
            $this->define('pricing/toggle', 'Render switchable monthly and yearly pricing cards.', ['plans']),
            $this->define('pricing/yearly', 'Render yearly pricing cards.', ['plans']),
            $this->define('process-steps/grid', 'Explain a process as numbered steps.', ['heading']),
            $this->define('process-steps/icon-grid', 'Explain a process with approved icons.', ['heading', 'steps']),
            $this->define('service-areas/list', 'List served communities without storefront claims.', ['heading', 'areas'], $this->copperEagleSectionFields('service-areas/list')),
            $this->define('service-cards/grid', 'Link to selected services.', ['heading']),
            $this->define('team/grid', 'Introduce verified team members.', ['heading', 'members']),
            $this->define('testimonials/carousel', 'Present attributable customer testimonials.', ['heading', 'items']),
            $this->define('testimonials/quotes', 'Present attributable customer quotes.', ['heading'], $this->copperEagleSectionFields('testimonials/quotes')),
            $this->define('trust-bar/cards', 'Present sourced trust statements.', ['items']),
            $this->define('trust-bar/google-rating', 'Present the maintained Google rating.'),

            $this->define('content/rich-text', 'Render structured long-form editorial content.', ['heading', 'nodes']),
            $this->define('content/direct-answer', 'Answer one search question directly and calmly.', ['heading', 'answer']),
            $this->define('content/inclusions-exclusions', 'Clarify what a service includes and excludes.', ['heading', 'included', 'excluded']),
            $this->define('content/comparison-table', 'Compare reader decision criteria in a semantic table.', ['heading', 'caption', 'columns', 'rows']),
            $this->define('content/table-of-contents', 'Link to validated headings on the current page.', ['heading', 'items']),
            $this->define('content/sources-reviewer', 'Disclose sources, review ownership, and freshness.', ['heading', 'sources']),
            $this->define('content/related-content', 'Link to relevant published content.', ['heading', 'items']),
            $this->define('pricing/factors', 'Explain factors that affect a quote without inventing prices.', ['heading', 'factors'], $this->copperEagleSectionFields('pricing/factors')),
            $this->define('before-after/slider', 'Compare sourced before and after images.', ['heading', 'before', 'after']),
            $this->define('photo-grid/gallery', 'Display an accessible project photo gallery.', ['heading', 'images']),
            $this->define('evidence/case-study', 'Present a verified customer problem, work, and outcome.', ['heading', 'problem', 'work', 'outcome']),
            $this->define('media/video', 'Present a controlled video with caption and transcript.', ['heading', 'video', 'poster', 'transcript']),
            $this->define('stats/proof', 'Present sourced proof statistics.', ['heading', 'items']),
            $this->define('logo-strip/credentials', 'Present sourced credentials or memberships.', ['heading', 'items']),
            $this->define('service-areas/grid', 'Link to served communities without office implications.', ['heading', 'areas']),
            $this->define('service-areas/coverage', 'Pair a coverage illustration with a crawlable served-area list.', ['heading', 'areas']),
            $this->define('evidence/local-project', 'Present a verified local project.', ['heading', 'place', 'work', 'outcome', 'source']),
            $this->define('cta/sticky-mobile', 'Keep one valid response action available on small screens.', ['primaryAction']),
            $this->define('cta/inline-band', 'Insert one contextual response between editorial sections.', ['heading', 'primaryAction'], $this->copperEagleSectionFields('cta/inline-band')),
            $this->define('cta/form-confirmation', 'Explain what happens after a form submission.', ['heading', 'steps']),
            $this->define('cta/visit-expectations', 'Set calm, accurate expectations for a service visit.', ['heading', 'items']),
            $this->define('hero/interior', 'Introduce a standard interior page with its only H1.', ['heading'], $this->copperEagleSectionFields('hero/interior')),
            $this->define('hero/service', 'Introduce a service pillar with its only H1.', ['heading', 'primaryAction']),
            $this->define('hero/location', 'Introduce service availability in a place without office claims.', ['heading', 'place', 'primaryAction']),
            $this->define('hero/article', 'Introduce editorial content and its accountable metadata.', ['heading', 'author', 'publishedAt']),

            // Copper Eagle page-template blocks. Contracts are declared in full
            // here rather than through acceptedFields/contractField.
            $this->define('hero/photo', 'Introduce the home page with its only H1 over a full-bleed photo, under the service icon strip.', ['heading', 'primaryAction', 'image'], [
                'emphasis' => $this->field('string', false, 'Optional exact phrase from the heading, rendered in the heavier weight.'),
                'body' => $this->contractField('hero/photo', 'body', false),
                'primaryAction' => $this->contractField('hero/photo', 'primaryAction', true),
                'image' => $this->contractField('hero/photo', 'image', true),
                'video' => $this->backgroundVideoField('Optional decorative background video over the photo, under the same overlay. Muted and looping; a reduced-motion visitor sees the poster. The photo stays the priority image.'),
                'serviceStrip' => $this->field('boolean', false, 'Whether the service icon strip renders under the header. Defaults to true.'),
            ]),
            $this->withoutFields($this->define('service-cards/icon-strip', 'Link to each service that has an icon, as the strip of line icons under the header.', [], [
                'services' => [
                    ...$this->field('array', false, 'Optional service record slugs in display order. Defaults to every service with an icon, in record order.'),
                    'shape' => 'list',
                    'items' => $this->scalarProperty('string', true),
                ],
            ]), ['eyebrow', 'heading']),
            $this->define('content/feature-cards', 'Present two to six round photo cards beside or under a heading.', ['heading', 'items'], [
                'emphasis' => $this->field('string', false, 'Optional exact phrase from the heading, rendered in the copper accent.'),
                'body' => $this->contractField('content/feature-cards', 'body', false),
                'items' => [
                    ...$this->field('array', true, 'Two to six cards, each with a title, a body, and an image. A blank line in a body starts a new paragraph.'),
                    'shape' => 'list',
                    'minItems' => 2,
                    'maxItems' => 6,
                    'items' => $this->objectProperty([
                        'title' => $this->scalarProperty('string', true),
                        'body' => $this->scalarProperty('string', true),
                        'image' => $this->objectProperty($this->mediaProperties()),
                    ]),
                ],
                'layout' => $this->field('string', false, 'Heading beside the cards (side, the default) or centred above them (stacked).', ['side', 'stacked']),
                'background' => $this->field('string', false, 'Section background. Defaults to white.', ['white', 'stone']),
                'watermark' => $this->field('boolean', false, 'Whether the eagle watermark sits behind a side-layout heading. Defaults to true.'),
            ]),
            $this->define('social/feed', 'Invite visitors to follow the business: profile handles beside a static grid of recent posts, each opening the post in a new tab.', ['heading'], [
                'body' => $this->field('string', false, 'Optional short line under the heading.'),
                'profiles' => [
                    ...$this->field('array', false, 'Optional profiles listed under the heading: the network as an uppercase label, the handle, and an optional absolute link to the profile.'),
                    'shape' => 'list',
                    'items' => $this->objectProperty([
                        'label' => $this->scalarProperty('string', true),
                        'handle' => $this->scalarProperty('string', true),
                        'href' => $this->scalarProperty('string', false),
                    ]),
                ],
                'posts' => [
                    ...$this->field('array', false, 'Up to '.self::SOCIAL_FEED_MAX_POSTS.' posts in display order, each an absolute link to the post and a square image whose alt text describes it.'),
                    'shape' => 'list',
                    'maxItems' => self::SOCIAL_FEED_MAX_POSTS,
                    'items' => $this->objectProperty([
                        'href' => $this->scalarProperty('string', true),
                        'image' => $this->objectProperty($this->mediaProperties()),
                    ]),
                ],
            ]),
            $this->define('social/profiles', 'Link to each social profile as an image tile, beside an optional contact card such as a vCard QR code.', ['heading', 'profiles'], [
                'body' => $this->field('string', false, 'Optional short line under the heading.'),
                'profiles' => [
                    ...$this->field('array', true, 'Profile tiles in display order, each an absolute link to the profile and an image whose alt text names it.'),
                    'shape' => 'list',
                    'items' => $this->objectProperty([
                        'href' => $this->scalarProperty('string', true),
                        'image' => $this->objectProperty($this->mediaProperties()),
                    ]),
                ],
                'card' => [
                    ...$this->field('array', false, 'Optional contact card beside the tiles: an image (such as a QR code), a short label under it, and a safe link (such as /s/contact.vcf).'),
                    'shape' => 'object',
                    'properties' => [
                        'label' => $this->scalarProperty('string', true),
                        'href' => $this->scalarProperty('string', true),
                        'image' => $this->objectProperty($this->mediaProperties()),
                    ],
                ],
            ]),
            $this->define('evidence/check-ins', 'Show the job check-ins the team posts: who did the work, the day, what they did, and photos from the job. Two show at first and the rest load on request. These are the team\'s own write-ups, never customer reviews.', ['heading', 'items'], [
                'items' => [
                    ...$this->field('array', true, 'Up to '.self::CHECK_INS_MAX_ITEMS.' check-ins, newest first. Each needs a write-up, photos, or both.'),
                    'shape' => 'list',
                    'maxItems' => self::CHECK_INS_MAX_ITEMS,
                    'items' => $this->objectProperty([
                        'author' => $this->scalarProperty('string', true),
                        'date' => $this->scalarProperty('string', true),
                        'body' => $this->scalarProperty('string', false),
                        'photos' => [
                            'type' => 'array',
                            'required' => false,
                            'shape' => 'list',
                            'maxItems' => self::CHECK_IN_MAX_PHOTOS,
                            'items' => $this->objectProperty($this->mediaProperties()),
                        ],
                    ]),
                ],
            ]),
        ];

        return collect($definitions)
            ->mapWithKeys(fn (BlockDefinition $definition): array => [$definition->type => $definition])
            ->all();
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<string, array<string, mixed>>  $overrides
     */
    private function define(
        string $type,
        string $purpose,
        array $required = [],
        array $overrides = [],
    ): BlockDefinition {
        $fields = [
            'eyebrow' => $this->field('string', false, 'Short section context.'),
            'heading' => $this->field('string', in_array('heading', $required, true), 'Visible section heading.'),
        ];

        if (array_key_exists($type, self::DEFAULT_ANCHORS)) {
            $fields = [
                'id' => $this->field('string', false, 'Stable section anchor.'),
                ...$fields,
            ];
        }

        foreach (array_unique([...$this->acceptedFields($type), ...$required]) as $field) {
            if (isset($fields[$field]) || isset($overrides[$field])) {
                continue;
            }

            $fields[$field] = $this->contractField(
                $type,
                $field,
                in_array($field, $required, true),
            );
        }

        foreach ($overrides as $field => $schema) {
            $fields[$field] = $schema;
        }

        return new BlockDefinition(
            type: $type,
            component: 'blocks.'.str_replace('/', '.', $type),
            purpose: $purpose,
            fields: $fields,
            examples: [],
        );
    }

    /**
     * Drop shared fields a renderer does not accept, so the live schema never
     * offers content that would silently disappear.
     *
     * @param  array<int, string>  $fields
     */
    private function withoutFields(BlockDefinition $definition, array $fields): BlockDefinition
    {
        return new BlockDefinition(
            type: $definition->type,
            component: $definition->component,
            purpose: $definition->purpose,
            fields: array_diff_key($definition->fields, array_flip($fields)),
            examples: $definition->examples,
        );
    }

    /**
     * Every Blade prop is part of the live schema contract, including fields
     * that are optional at render time.
     *
     * @return array<int, string>
     */
    private function acceptedFields(string $type): array
    {
        return [
            'announcement/bar' => ['message', 'url', 'linkLabel'],
            'content/benefit-carousel' => [],
            'content/capability-strip' => ['label', 'items'],
            'content/commitment-collage' => ['body', 'points', 'cta', 'images', 'collageLabel'],
            'content/resource-cards' => ['summaryLabel', 'items'],
            'content/split' => ['body', 'checklist', 'image', 'inset', 'stamp'],
            'content/story-checklist' => ['body', 'items', 'image', 'inset'],
            'cta/image-split' => ['body', 'cta', 'image'],
            'cta/panel' => ['cta', 'image'],
            'cta/quote-form-split' => ['body', 'image', 'submitLabel', 'showMessage', 'purpose'],
            'cta/quote-form' => ['body', 'purpose'],
            'faq/accordion' => ['service', 'area', 'items'],
            'faq/highlighted' => ['body', 'service', 'area', 'items', 'openIndex'],
            'hero/split' => ['body', 'cta', 'secondary', 'image', 'video', 'badge'],
            'pricing/monthly' => ['body', 'badge', 'plans'],
            'pricing/toggle' => ['body', 'defaultMode', 'modes', 'plans'],
            'pricing/yearly' => ['body', 'badge', 'plans'],
            'process-steps/grid' => ['steps'],
            'process-steps/icon-grid' => ['body', 'steps'],
            'service-areas/list' => ['areas'],
            'service-cards/grid' => ['services'],
            'team/grid' => ['body', 'members'],
            'testimonials/carousel' => ['service', 'area', 'items'],
            'testimonials/quotes' => ['service', 'area', 'items'],
            'trust-bar/cards' => ['label', 'items'],
            'trust-bar/google-rating' => [],
            'content/rich-text' => ['nodes', 'media'],
            'content/direct-answer' => ['answer', 'supporting', 'source', 'nextStep', 'media', 'mediaSide'],
            'content/inclusions-exclusions' => ['included', 'excluded'],
            'content/comparison-table' => ['caption', 'columns', 'rows'],
            'content/table-of-contents' => ['items'],
            'content/sources-reviewer' => ['sources', 'reviewer'],
            'content/related-content' => ['items'],
            'pricing/factors' => ['factors'],
            'before-after/slider' => ['before', 'after'],
            'photo-grid/gallery' => ['images'],
            'evidence/case-study' => ['problem', 'work', 'outcome', 'facts', 'image'],
            'media/video' => ['video', 'poster', 'transcript', 'caption'],
            'stats/proof' => ['items'],
            'logo-strip/credentials' => ['items'],
            'service-areas/grid' => ['areas'],
            'service-areas/coverage' => ['body', 'coverageLabel', 'areas'],
            'evidence/local-project' => ['place', 'work', 'outcome', 'source', 'image'],
            'cta/sticky-mobile' => ['primaryAction'],
            'cta/inline-band' => ['body', 'primaryAction', 'secondaryAction'],
            'cta/form-confirmation' => ['body', 'steps'],
            'cta/visit-expectations' => ['body', 'items'],
            'hero/interior' => ['body', 'primaryAction', 'secondaryAction'],
            'hero/service' => ['body', 'primaryAction', 'secondaryAction', 'image'],
            'hero/location' => ['place', 'body', 'primaryAction', 'secondaryAction'],
            'hero/article' => ['summary', 'author', 'publishedAt', 'updatedAt', 'reviewer'],
        ][$type] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractField(
        string $type,
        string $field,
        bool $required,
    ): array {
        if ($field === 'purpose') {
            return $this->field('string', $required, 'Server-owned form destination purpose.', ['inquiry', 'feedback', 'comments']);
        }

        if ($field === 'showMessage') {
            return $this->field('boolean', $required, 'Whether the optional message field is shown.');
        }

        if ($field === 'openIndex') {
            return $this->field('integer', $required, 'Zero-based item initially expanded.');
        }

        /*
         * Dates are strings with a constraint validation enforces, so the live
         * schema has to say so. A client discovering hero/article otherwise
         * learns only "string" and finds out about YYYY-MM-DD by being rejected.
         */
        if (in_array($field, ['publishedAt', 'updatedAt'], true)) {
            return [
                ...$this->field(
                    'string',
                    $required,
                    ($required ? 'Required' : 'Optional')." {$field} date.",
                ),
                'format' => 'date',
            ];
        }

        if ($type === 'content/split' && $field === 'body') {
            return $this->field(
                'string',
                $required,
                ($required ? 'Required' : 'Optional').' body text. A blank line starts a new paragraph.',
            );
        }

        if (in_array($field, [
            'message', 'url', 'linkLabel', 'label', 'body', 'collageLabel',
            'summaryLabel', 'submitLabel', 'service', 'area', 'badge',
            'defaultMode', 'mediaSide', 'stamp', 'caption', 'place', 'problem', 'work',
            'outcome', 'transcript', 'coverageLabel', 'summary', 'author',
        ], true)) {
            $allowed = match ($field) {
                'defaultMode' => ['monthly', 'yearly'],
                'mediaSide' => ['start', 'end'],
                default => null,
            };

            return $this->field(
                'string',
                $required,
                ($required ? 'Required' : 'Optional')." {$field} content.",
                $allowed,
            );
        }

        if ($field === 'modes') {
            return [
                ...$this->field('array', $required, 'Labels and badges for each pricing mode.'),
                'shape' => 'object',
                'properties' => [
                    'monthly' => $this->objectProperty([
                        'label' => $this->scalarProperty('string', true),
                        'badge' => $this->scalarProperty('string', false),
                    ]),
                    'yearly' => $this->objectProperty([
                        'label' => $this->scalarProperty('string', true),
                        'badge' => $this->scalarProperty('string', false),
                    ]),
                ],
            ];
        }

        if (in_array($field, [
            'cta', 'secondary', 'primaryAction', 'secondaryAction',
            'source', 'nextStep',
        ], true)) {
            return [
                ...$this->field('array', $required, 'A controlled link object.'),
                'shape' => 'object',
                'properties' => [
                    'label' => $this->scalarProperty('string', true),
                    'href' => $this->scalarProperty('string', true),
                ],
            ];
        }

        if (in_array($field, ['image', 'inset', 'before', 'after', 'poster'], true)) {
            return [
                ...$this->field('array', $required, 'An image reference or safe image source.'),
                'shape' => 'object',
                'properties' => $this->mediaProperties(),
            ];
        }

        if ($field === 'video') {
            return [
                ...$this->field('array', $required, 'A controlled video source.'),
                'shape' => 'object',
                'properties' => [
                    'src' => $this->scalarProperty('string', true),
                    'type' => [
                        ...$this->scalarProperty('string', true),
                        'allowed' => ['video/mp4', 'video/webm', 'video/ogg'],
                    ],
                ],
            ];
        }

        if ($field === 'reviewer') {
            return [
                ...$this->field('array', $required, 'A named reviewer and review date.'),
                'shape' => 'object',
                'properties' => [
                    'name' => $this->scalarProperty('string', true),
                    'role' => $this->scalarProperty(
                        'string',
                        $type === 'content/sources-reviewer',
                    ),
                    'reviewed_at' => [
                        ...$this->scalarProperty('string', true),
                        'format' => 'date',
                    ],
                ],
            ];
        }

        $description = match ("{$type}.{$field}") {
            'content/split.checklist' => 'Optional short points with copper checks. Each is a string, or a label and text pair whose bold label sits above the text.',
            'faq/accordion.items' => 'Required questions and answers. A blank line in an answer starts a new paragraph.',
            default => ($required ? 'Required' : 'Optional')." {$field} collection.",
        };

        return [
            ...$this->field('array', $required, $description),
            'shape' => 'list',
            'items' => $this->listItemContract($type, $field),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItemContract(string $type, string $field): array
    {
        if ($type === 'content/split' && $field === 'checklist') {
            return [
                'type' => 'string|array',
                'required' => true,
                'shape' => 'object',
                'properties' => [
                    'label' => $this->scalarProperty('string', true),
                    'text' => $this->scalarProperty('string', true),
                ],
            ];
        }

        if (in_array($field, [
            'points',
            'checklist',
            'included',
            'excluded',
            'items',
            'steps',
        ], true) && in_array($type, [
            'content/capability-strip',
            'content/story-checklist',
            'content/inclusions-exclusions',
            'cta/form-confirmation',
        ], true)) {
            return $this->scalarProperty('string', true);
        }

        if (in_array($field, ['images', 'media'], true)) {
            return $this->objectProperty($this->mediaProperties());
        }

        if (in_array($field, ['nodes', 'answer', 'supporting'], true)) {
            return [
                'type' => 'object',
                'contract' => $field === 'nodes'
                    ? 'rich_content.nodes'
                    : 'rich_content.inline_segments',
            ];
        }

        $properties = match ("{$type}.{$field}") {
            'faq/accordion.items', 'faq/highlighted.items' => [
                'question' => $this->scalarProperty('string', true),
                'answer' => $this->scalarProperty('string', true),
            ],
            'process-steps/grid.steps' => [
                'number' => $this->scalarProperty('string', true),
                'title' => $this->scalarProperty('string', true),
                'body' => $this->scalarProperty('string', true),
            ],
            'process-steps/icon-grid.steps' => [
                'number' => $this->scalarProperty('string', false),
                'icon' => [
                    ...$this->scalarProperty('string', true),
                    'allowed' => array_keys(MarketingIcon::catalog()),
                ],
                'title' => $this->scalarProperty('string', true),
                'body' => $this->scalarProperty('string', true),
            ],
            'service-cards/grid.services' => [
                'title' => $this->scalarProperty('string', true),
                'body' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', false),
                'image' => $this->objectProperty($this->mediaProperties(), false),
            ],
            'testimonials/carousel.items' => [
                'type' => [
                    ...$this->scalarProperty('string', false),
                    'allowed' => ['quote', 'video'],
                ],
                'title' => $this->scalarProperty('string', false),
                'quote' => $this->scalarProperty('string', false),
                'author' => $this->scalarProperty('string', false),
                'role' => $this->scalarProperty('string', false),
                'context' => $this->scalarProperty('string', false),
                'rating' => $this->scalarProperty('integer', false),
                'avatar' => $this->objectProperty($this->mediaProperties(), false),
                'poster' => $this->objectProperty($this->mediaProperties(), false),
                'video_url' => $this->scalarProperty('string', false),
                'transcript' => $this->scalarProperty('string', false),
            ],
            'testimonials/quotes.items' => [
                'title' => $this->scalarProperty('string', false),
                'quote' => $this->scalarProperty('string', true),
                'author' => $this->scalarProperty('string', true),
                'role' => $this->scalarProperty('string', false),
                'location' => $this->scalarProperty('string', false),
                'rating' => $this->scalarProperty('integer', false),
            ],
            'content/resource-cards.items' => [
                'title' => $this->scalarProperty('string', true),
                'body' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', false),
                'image' => $this->objectProperty($this->mediaProperties(), false),
            ],
            'content/comparison-table.columns' => [
                'key' => $this->scalarProperty('string', true),
                'label' => $this->scalarProperty('string', true),
            ],
            'content/comparison-table.rows' => [
                'label' => $this->scalarProperty('string', true),
                'values' => [
                    ...$this->objectProperty([], true),
                    'additionalProperties' => $this->scalarProperty('string', true),
                ],
            ],
            'content/table-of-contents.items' => [
                'label' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', true),
            ],
            'content/sources-reviewer.sources' => [
                'label' => $this->scalarProperty('string', true),
                'url' => $this->scalarProperty('string', true),
                'publisher' => $this->scalarProperty('string', false),
                'reviewed_at' => [
                    ...$this->scalarProperty('string', true),
                    'format' => 'date',
                ],
            ],
            'content/related-content.items' => [
                'title' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', true),
                'type' => $this->scalarProperty('string', false),
                'summary' => $this->scalarProperty('string', false),
            ],
            'pricing/factors.factors' => [
                'label' => $this->scalarProperty('string', true),
                'description' => $this->scalarProperty('string', true),
            ],
            'pricing/monthly.plans', 'pricing/toggle.plans', 'pricing/yearly.plans' => [
                'name' => $this->scalarProperty('string', true),
                'summary' => $this->scalarProperty('string', false),
                'prices' => $this->objectProperty([
                    'monthly' => $this->objectProperty([
                        'amount' => $this->scalarProperty('number|string', true),
                        'suffix' => $this->scalarProperty('string', false),
                        'note' => $this->scalarProperty('string', false),
                    ], false),
                    'yearly' => $this->objectProperty([
                        'amount' => $this->scalarProperty('number|string', true),
                        'suffix' => $this->scalarProperty('string', false),
                        'note' => $this->scalarProperty('string', false),
                    ], false),
                ]),
                'featureHeading' => $this->scalarProperty('string', false),
                'features' => [
                    'type' => 'array',
                    'required' => false,
                    'shape' => 'list',
                    'items' => $this->scalarProperty('string', true),
                ],
                'cta' => $this->objectProperty([
                    'label' => $this->scalarProperty('string', true),
                    'href' => $this->scalarProperty('string', true),
                ], false),
                'media' => $this->objectProperty($this->mediaProperties(), false),
                'featured' => $this->scalarProperty('boolean', false),
            ],
            'team/grid.members' => [
                'name' => $this->scalarProperty('string', true),
                'role' => $this->scalarProperty('string', true),
                'bio' => $this->scalarProperty('string', false),
                'image' => $this->objectProperty($this->mediaProperties()),
                'linkedin_url' => $this->scalarProperty('string', false),
            ],
            'stats/proof.items' => [
                'value' => $this->scalarProperty('string', true),
                'label' => $this->scalarProperty('string', true),
                'source_label' => $this->scalarProperty('string', true),
                'source_url' => $this->scalarProperty('string', true),
            ],
            'logo-strip/credentials.items' => [
                'name' => $this->scalarProperty('string', true),
                'image' => $this->objectProperty($this->mediaProperties()),
                'source_label' => $this->scalarProperty('string', true),
                'source_url' => $this->scalarProperty('string', true),
            ],
            'service-areas/list.areas', 'service-areas/grid.areas', 'service-areas/coverage.areas' => [
                'name' => $this->scalarProperty('string', true),
                'href' => $this->scalarProperty('string', true),
            ],
            'evidence/case-study.facts' => [
                'label' => $this->scalarProperty('string', true),
                'value' => $this->scalarProperty('string', true),
                'source_label' => $this->scalarProperty('string', true),
                'source_url' => $this->scalarProperty('string', true),
            ],
            'cta/visit-expectations.items' => [
                'icon' => $this->scalarProperty('string', false),
                'label' => $this->scalarProperty('string', true),
                'description' => $this->scalarProperty('string', true),
            ],
            default => [
                'title' => $this->scalarProperty('string', false),
                'body' => $this->scalarProperty('string', false),
                'label' => $this->scalarProperty('string', false),
            ],
        };

        return $this->objectProperty($properties);
    }

    /**
     * @return array<string, mixed>
     */
    private function backgroundVideoField(string $description): array
    {
        return [
            ...$this->field('array', false, $description),
            'shape' => 'object',
            'properties' => [
                'src' => $this->scalarProperty('string', true),
                'poster' => $this->objectProperty([
                    'src' => $this->scalarProperty('string', true),
                    'width' => $this->scalarProperty('integer', true),
                    'height' => $this->scalarProperty('integer', true),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mediaProperties(): array
    {
        return [
            'media_id' => $this->scalarProperty('integer', false),
            'src' => $this->scalarProperty('string', false),
            'alt' => $this->scalarProperty('string', false),
            'width' => $this->scalarProperty('integer', false),
            'height' => $this->scalarProperty('integer', false),
            'caption' => $this->scalarProperty('string', false),
            'source_label' => $this->scalarProperty('string', false),
            'source_url' => $this->scalarProperty('string', false),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private function objectProperty(array $properties, bool $required = true): array
    {
        return [
            'type' => 'array',
            'required' => $required,
            'shape' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarProperty(string $type, bool $required): array
    {
        return ['type' => $type, 'required' => $required];
    }

    /**
     * @param  array<int, mixed>|null  $allowed
     * @return array<string, mixed>
     */
    private function field(
        string $type,
        bool $required,
        string $description,
        ?array $allowed = null,
    ): array {
        return array_filter([
            'type' => $type,
            'required' => $required,
            'description' => $description,
            'allowed' => $allowed,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateSchemaValue(
        mixed $value,
        array $schema,
        string $pointer,
        array &$errors,
    ): void {
        $type = $schema['type'] ?? null;

        if (is_string($type) && ! $this->matchesType($value, $type)) {
            $errors[$pointer][] = "This field must be {$type}.";

            return;
        }

        if (isset($schema['allowed'])
            && ! in_array($value, $schema['allowed'], true)) {
            $errors[$pointer][] = 'The selected value is invalid.';
        }

        if (is_string($value)
            && ($schema['required'] ?? false)
            && trim($value) === '') {
            $errors[$pointer][] = 'This field is required.';

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $shape = $schema['shape'] ?? null;

        if ($shape === 'list') {
            if (! array_is_list($value)) {
                $errors[$pointer][] = 'This field must be an ordered array.';

                return;
            }

            $itemSchema = $schema['items'] ?? null;

            if (is_array($itemSchema)) {
                foreach ($value as $index => $item) {
                    $this->validateSchemaValue(
                        $item,
                        $itemSchema,
                        "{$pointer}/{$index}",
                        $errors,
                    );
                }
            }

            return;
        }

        if ($shape !== 'object') {
            return;
        }

        if ($value !== [] && array_is_list($value)) {
            $errors[$pointer][] = 'This field must be an object.';

            return;
        }

        $properties = $schema['properties'] ?? [];
        $additionalProperties = $schema['additionalProperties'] ?? null;

        foreach (array_diff(array_keys($value), array_keys($properties)) as $field) {
            $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $field);

            if (is_array($additionalProperties)) {
                $this->validateSchemaValue(
                    $value[$field],
                    $additionalProperties,
                    "{$pointer}/{$segment}",
                    $errors,
                );

                continue;
            }

            $errors["{$pointer}/{$segment}"][] = 'This property is not part of the selected object contract.';
        }

        foreach ($properties as $field => $propertySchema) {
            if (! is_array($propertySchema)) {
                continue;
            }

            $present = array_key_exists($field, $value)
                && $this->fieldIsPresent($value[$field], $propertySchema);
            $childPointer = $pointer.'/'.$field;

            if (($propertySchema['required'] ?? false) && ! $present) {
                $errors[$childPointer][] = "The {$field} field is required.";

                continue;
            }

            if ($present) {
                $this->validateSchemaValue(
                    $value[$field],
                    $propertySchema,
                    $childPointer,
                    $errors,
                );
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        if (str_contains($type, '|')) {
            return collect(explode('|', $type))->contains(
                fn (string $candidate): bool => $this->matchesType($value, $candidate),
            );
        }

        return match ($type) {
            'array' => is_array($value),
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'object' => is_array($value),
            'string' => is_string($value),
            default => false,
        };
    }
}
