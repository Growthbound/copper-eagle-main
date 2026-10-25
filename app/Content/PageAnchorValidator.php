<?php

namespace App\Content;

use App\Content\Validation\ContentValidationResult;

final readonly class PageAnchorValidator
{
    /** @var array<int, string> */
    private const RESERVED_PAGE_IDS = ['main-content', 'contact'];

    public function __construct(private BlockRegistry $blocks) {}

    /**
     * @param  array<int, array{type: string, props: array<string, mixed>, pointer: string}>  $sections
     */
    public function validate(array $sections): ContentValidationResult
    {
        $errors = [];
        $ids = array_fill_keys(self::RESERVED_PAGE_IDS, 1);

        foreach ($sections as $section) {
            $props = $section['props'];
            $pointer = $section['pointer'];
            $explicit = $props['id'] ?? null;

            if ($explicit !== null
                && (! is_string($explicit)
                    || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $explicit) !== 1)) {
                $errors["{$pointer}/id"][] = 'The section id must be a valid page anchor.';

                continue;
            }

            foreach ($this->blocks->emittedIds($section['type'], $props) as $id) {
                if (isset($ids[$id])) {
                    $errors["{$pointer}/id"][] = "The rendered id {$id} is already used on this page.";
                }

                $ids[$id] = ($ids[$id] ?? 0) + 1;
            }
        }

        foreach ($sections as $section) {
            foreach ($this->fragmentLinks($section['props'], $section['pointer']) as $link) {
                if (($ids[substr($link['href'], 1)] ?? 0) !== 1) {
                    $errors[$link['pointer']][] =
                        'The fragment link must target one unique rendered id on this page.';
                }
            }
        }

        return new ContentValidationResult($errors);
    }

    /**
     * @return array<int, array{href: string, pointer: string}>
     */
    private function fragmentLinks(mixed $value, string $pointer): array
    {
        if (! is_array($value)) {
            return [];
        }

        $links = [];

        foreach ($value as $key => $child) {
            $childPointer = $pointer.'/'.$this->pointerSegment((string) $key);

            if ($key === 'href'
                && is_string($child)
                && str_starts_with($child, '#')) {
                $links[] = ['href' => $child, 'pointer' => $childPointer];
            }

            array_push($links, ...$this->fragmentLinks($child, $childPointer));
        }

        return $links;
    }

    private function pointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
