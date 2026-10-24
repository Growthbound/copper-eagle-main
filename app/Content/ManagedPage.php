<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Models\ContentEntry;
use App\Models\ContentRevision;

final class ManagedPage
{
    /**
     * @param  array<int, Block>  $blocks
     * @param  array<string, Block>  $sections
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs
     */
    public function __construct(
        public readonly string $slug,
        public readonly ContentKind $kind,
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $noindex,
        public readonly array $blocks,
        public readonly PageTemplate $template,
        public readonly array $sections,
        public readonly ?string $canonical,
        public readonly ?array $socialImage,
        public readonly array $breadcrumbs,
        public readonly ?ContentEntry $entry,
        public readonly ?ContentRevision $revision,
    ) {}

    public static function fromRevision(
        ContentEntry $entry,
        ContentRevision $revision,
    ): self {
        $payload = $revision->payload;
        $identity = is_array($revision->identity) ? $revision->identity : [];

        return self::fromContent(
            identity: $identity,
            payload: $payload,
            fallbackKind: $entry->kind,
            fallbackSlug: $entry->slug,
            fallbackTemplate: $entry->template,
            entry: $entry,
            revision: $revision,
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    public static function fromDocument(array $identity, array $payload): self
    {
        return self::fromContent(
            identity: $identity,
            payload: $payload,
            fallbackKind: ContentKind::from((string) $identity['kind']),
            fallbackSlug: (string) $identity['slug'],
            fallbackTemplate: PageTemplate::from((string) $identity['template']),
            entry: null,
            revision: null,
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $payload
     */
    private static function fromContent(
        array $identity,
        array $payload,
        ContentKind $fallbackKind,
        string $fallbackSlug,
        PageTemplate $fallbackTemplate,
        ?ContentEntry $entry,
        ?ContentRevision $revision,
    ): self {
        $templateValue = $identity['template'] ?? $fallbackTemplate;
        $template = $templateValue instanceof PageTemplate
            ? $templateValue
            : PageTemplate::from((string) $templateValue);
        $data = app(BlockData::class);
        $blocksRegistry = app(BlockRegistry::class);
        $blocks = collect($payload['blocks'] ?? [])
            ->filter(fn (mixed $block): bool => is_array($block)
                && is_string($block['type'] ?? null)
                && is_array($block['props'] ?? null))
            ->map(fn (array $block): Block => new Block(
                $block['type'],
                $blocksRegistry->renderProps($block['type'], $block['props']),
            ))
            ->values()
            ->all();
        $sections = $template === PageTemplate::Standard
            ? []
            : app(FixedPageAssembler::class)->assemble(
                $template,
                is_array($payload['sections'] ?? null) ? $payload['sections'] : [],
            );

        return new self(
            slug: (string) ($identity['slug'] ?? $fallbackSlug),
            kind: ($identity['kind'] ?? $fallbackKind) instanceof ContentKind
                ? ($identity['kind'] ?? $fallbackKind)
                : ContentKind::from((string) ($identity['kind'] ?? $fallbackKind)),
            title: trim((string) $payload['title']),
            description: isset($payload['description'])
                && is_string($payload['description'])
                && trim($payload['description']) !== ''
                    ? trim($payload['description'])
                    : null,
            noindex: (bool) ($payload['noindex'] ?? false),
            blocks: $template === PageTemplate::Standard ? $blocks : array_values($sections),
            template: $template,
            sections: $sections,
            canonical: self::canonical($payload['canonical'] ?? null),
            socialImage: $data->media($payload['social_image'] ?? null),
            breadcrumbs: self::breadcrumbs($payload['breadcrumbs'] ?? null),
            entry: $entry,
            revision: $revision,
        );
    }

    public function hasBlockCategory(string $category): bool
    {
        foreach ($this->blocks as $block) {
            if (str_starts_with($block->type, $category.'/')) {
                return true;
            }
        }

        return false;
    }

    public function url(): string
    {
        return $this->slug === '/' ? url('/') : url($this->slug);
    }

    public function canonicalUrl(): string
    {
        if ($this->canonical === null) {
            return $this->url();
        }

        return str_starts_with($this->canonical, '/')
            ? rtrim(url('/'), '/').$this->canonical
            : $this->canonical;
    }

    public function publishedAt(): ?string
    {
        foreach ($this->blocks as $block) {
            if ($block->type === 'hero/article'
                && is_string($block->props['publishedAt'] ?? null)) {
                return $block->props['publishedAt'];
            }
        }

        return null;
    }

    private static function canonical(mixed $canonical): ?string
    {
        if (! is_string($canonical) || trim($canonical) === '') {
            return null;
        }

        return trim($canonical);
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private static function breadcrumbs(mixed $breadcrumbs): array
    {
        if (! is_array($breadcrumbs)) {
            return [];
        }

        return collect($breadcrumbs)
            ->filter(fn (mixed $crumb): bool => is_array($crumb)
                && is_string($crumb['name'] ?? null)
                && trim($crumb['name']) !== ''
                && is_string($crumb['url'] ?? null))
            ->map(fn (array $crumb): array => [
                'name' => trim($crumb['name']),
                'url' => str_starts_with($crumb['url'], '/')
                    ? url($crumb['url'])
                    : $crumb['url'],
            ])
            ->values()
            ->all();
    }
}
