<?php

namespace App\Content;

use JsonException;

/**
 * A page assembled from blocks.
 *
 * Pages are JSON files under content/pages so they move through Git with the
 * rest of the site: reviewable in a pull request, revertable with a commit.
 */
final class Page
{
    /**
     * @param  array<int, Block>  $blocks
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $description,
        public readonly bool $noindex,
        public readonly array $blocks,
        public readonly string $sourcePath,
    ) {}

    /**
     * @throws InvalidPage when the file is unreadable, is not valid JSON, or
     *                     is missing the structure every page needs.
     */
    public static function fromFile(string $absolutePath, string $slug): self
    {
        $raw = @file_get_contents($absolutePath);

        if ($raw === false) {
            throw new InvalidPage($slug, 'The file could not be read.');
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidPage($slug, 'The file is not valid JSON: '.$e->getMessage());
        }

        if (! is_array($data)) {
            throw new InvalidPage($slug, 'The file must contain a JSON object.');
        }

        if (! isset($data['title']) || ! is_string($data['title']) || trim($data['title']) === '') {
            throw new InvalidPage($slug, 'A page needs a "title".');
        }

        if (! isset($data['blocks']) || ! array_is_list($data['blocks'] ?? null ?: [])) {
            throw new InvalidPage($slug, 'A page needs a "blocks" array, even if it is empty.');
        }

        return new self(
            slug: $slug,
            title: trim($data['title']),
            description: isset($data['description']) && is_string($data['description'])
                ? trim($data['description'])
                : null,
            noindex: (bool) ($data['noindex'] ?? false),
            blocks: self::readBlocks($data['blocks'], $slug),
            sourcePath: $absolutePath,
        );
    }

    /**
     * Whether this page renders a block of the given category, used to decide
     * which structured data the page should also emit.
     */
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

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, Block>
     */
    private static function readBlocks(array $blocks, string $slug): array
    {
        return array_map(function (mixed $block, int $index) use ($slug): Block {
            if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
                throw new InvalidPage($slug, 'Block '.($index + 1).' needs a "type" such as "hero/split".');
            }

            $props = $block['props'] ?? [];

            if (! is_array($props)) {
                throw new InvalidPage($slug, 'Block '.($index + 1).' has a "props" value that is not an object.');
            }

            return new Block($block['type'], $props);
        }, $blocks, array_keys($blocks));
    }
}
