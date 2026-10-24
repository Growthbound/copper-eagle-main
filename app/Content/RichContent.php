<?php

namespace App\Content;

use App\Content\Enums\RichNodeType;
use App\Content\Validation\ContentValidationResult;
use App\Enums\MediaKind;
use App\Models\Media;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RichContent
{
    /** @var array<int, string> */
    public const MARKS = ['plain', 'strong', 'emphasis', 'code'];

    /** @var array<int, string> */
    public const IMAGE_LAYOUTS = ['inline', 'wide'];

    /** @var array<int, string> */
    public const CALLOUT_TONES = ['note', 'tip', 'caution'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizeNodes(mixed $nodes): array
    {
        if (! is_array($nodes)) {
            return [];
        }

        $normalized = [];

        foreach ($nodes as $node) {
            $candidate = $this->normalizeNode($node);
            $errors = [];

            if ($candidate !== null) {
                $type = RichNodeType::tryFrom((string) ($candidate['type'] ?? ''));

                if ($type !== null) {
                    $this->validateNode(
                        $type,
                        $candidate,
                        '/node',
                        $errors,
                        validateMediaCatalog: false,
                    );
                }
            }

            if ($candidate !== null && $errors === []) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }

    public function validateNodes(
        mixed $nodes,
        string $pointer = '/nodes',
    ): ContentValidationResult {
        if (! is_array($nodes)) {
            return new ContentValidationResult([
                $pointer => ['The nodes field must be an array.'],
            ]);
        }

        $errors = [];

        $previousHeadingLevel = null;

        foreach ($nodes as $index => $node) {
            $nodePointer = "{$pointer}/{$index}";

            if (! is_array($node)) {
                $errors[$nodePointer][] = 'The content node must be an object.';

                continue;
            }

            $type = is_string($node['type'] ?? null)
                ? RichNodeType::tryFrom($node['type'])
                : null;

            if ($type === null) {
                $errors["{$nodePointer}/type"][] = 'The selected content node type is invalid.';

                continue;
            }

            $this->validateNode($type, $node, $nodePointer, $errors);

            if ($type === RichNodeType::Heading
                && is_int($node['level'] ?? null)
                && in_array($node['level'], [3, 4, 5, 6], true)) {
                if ($previousHeadingLevel === null && $node['level'] !== 3) {
                    $errors["{$nodePointer}/level"][] = 'The first rich-content heading must be level 3.';
                } elseif ($previousHeadingLevel !== null
                    && $node['level'] > $previousHeadingLevel + 1) {
                    $errors["{$nodePointer}/level"][] = 'Rich-content headings cannot skip a level.';
                }

                $previousHeadingLevel = $node['level'];
            }
        }

        return new ContentValidationResult($errors);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function normalizeSegments(mixed $segments): array
    {
        if (! is_array($segments)) {
            return [];
        }

        $normalized = [];

        foreach ($segments as $segment) {
            if (! is_array($segment) || ! is_string($segment['text'] ?? null)) {
                continue;
            }

            $item = ['text' => $segment['text']];

            if (isset($segment['mark'])
                && is_string($segment['mark'])
                && in_array($segment['mark'], self::MARKS, true)
                && $segment['mark'] !== 'plain') {
                $item['mark'] = $segment['mark'];
            }

            if (isset($segment['href']) && $this->isSafeUrl($segment['href'])) {
                $item['href'] = $segment['href'];
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    public function validateDirectAnswer(
        mixed $segments,
        string $pointer = '/answer',
    ): ContentValidationResult {
        $errors = $this->segmentErrors($segments, $pointer);
        $warnings = [];

        if ($errors === []) {
            $words = preg_split(
                '/\s+/u',
                trim(collect($segments)->pluck('text')->implode(' ')),
                -1,
                PREG_SPLIT_NO_EMPTY,
            ) ?: [];
            $count = count($words);

            if ($count < 40 || $count > 70) {
                $warnings[$pointer][] = 'Direct answers should contain 40 to 70 words.';
            }
        }

        return new ContentValidationResult($errors, $warnings);
    }

    public function validateSegments(
        mixed $segments,
        string $pointer = '/segments',
    ): ContentValidationResult {
        return new ContentValidationResult(
            $this->segmentErrors($segments, $pointer),
        );
    }

    public function isSafeUrl(mixed $url): bool
    {
        if (! is_string($url)
            || $url === ''
            || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        if (str_starts_with($url, '#')) {
            return strlen($url) > 1;
        }

        if (str_starts_with($url, 'mailto:')) {
            return filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        if (str_starts_with($url, 'tel:')) {
            $number = substr($url, 4);

            return preg_match('/^\+?[0-9(). -]+$/', $number) === 1
                && preg_match('/\d/', $number) === 1;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public function isSafeMediaUrl(mixed $url): bool
    {
        if (! $this->isSafeUrl($url) || ! is_string($url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return in_array(
            strtolower((string) parse_url($url, PHP_URL_SCHEME)),
            ['http', 'https'],
            true,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeNode(mixed $node): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return null;
        }

        $type = RichNodeType::tryFrom($node['type']);

        if ($type === null) {
            return null;
        }

        return match ($type) {
            RichNodeType::Heading => $this->only($node, ['type', 'level', 'text', 'id']),
            RichNodeType::Paragraph => [
                'type' => $type->value,
                'segments' => $this->normalizeSegments($node['segments'] ?? null),
            ],
            RichNodeType::UnorderedList => [
                'type' => $type->value,
                'items' => $this->normalizeItems($node['items'] ?? null),
            ],
            RichNodeType::OrderedList => $this->orderedList($node),
            RichNodeType::Image => $this->normalizeImage($node),
            RichNodeType::Callout => [
                ...$this->only($node, ['type', 'tone', 'title']),
                'segments' => $this->normalizeSegments($node['segments'] ?? null),
            ],
            RichNodeType::Quote => $this->normalizeQuote($node),
            RichNodeType::Divider => ['type' => $type->value],
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function orderedList(array $node): array
    {
        $normalized = [
            'type' => RichNodeType::OrderedList->value,
            'items' => $this->normalizeItems($node['items'] ?? null),
        ];

        if (array_key_exists('start', $node)) {
            $normalized['start'] = $node['start'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeImage(array $node): array
    {
        $normalized = $this->only($node, [
            'type',
            'media_id',
            'alt',
            'width',
            'height',
            'caption',
            'layout',
        ]);

        if ($this->isSafeMediaUrl($node['src'] ?? null)) {
            $normalized['src'] = $node['src'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeQuote(array $node): array
    {
        $normalized = $this->only($node, [
            'type',
            'quote',
            'attribution',
            'role',
        ]);

        if ($this->isSafeUrl($node['source_url'] ?? null)) {
            $normalized['source_url'] = $node['source_url'];
        }

        return $normalized;
    }

    /**
     * @return array<int, array<int, array<string, string>>>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn (mixed $item): array => $this->normalizeSegments($item))
            ->filter(fn (array $item): bool => $item !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateNode(
        RichNodeType $type,
        array $node,
        string $pointer,
        array &$errors,
        bool $validateMediaCatalog = true,
    ): void {
        match ($type) {
            RichNodeType::Heading => $this->validateHeading($node, $pointer, $errors),
            RichNodeType::Paragraph => $this->mergeErrors(
                $errors,
                $this->segmentErrors($node['segments'] ?? null, "{$pointer}/segments"),
            ),
            RichNodeType::UnorderedList,
            RichNodeType::OrderedList => $this->validateList($type, $node, $pointer, $errors),
            RichNodeType::Image => $this->validateImage(
                $node,
                $pointer,
                $errors,
                $validateMediaCatalog,
            ),
            RichNodeType::Callout => $this->validateCallout($node, $pointer, $errors),
            RichNodeType::Quote => $this->validateQuote($node, $pointer, $errors),
            RichNodeType::Divider => null,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateHeading(array $node, string $pointer, array &$errors): void
    {
        if (! is_int($node['level'] ?? null)
            || ! in_array($node['level'], [3, 4, 5, 6], true)) {
            $errors["{$pointer}/level"][] = 'Rich-content headings must use level 3 through 6.';
        }

        if (! is_string($node['text'] ?? null) || trim($node['text']) === '') {
            $errors["{$pointer}/text"][] = 'The heading text is required.';
        }

        if (isset($node['id'])
            && (! is_string($node['id'])
                || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $node['id']) !== 1)) {
            $errors["{$pointer}/id"][] = 'The heading id must be a safe lowercase anchor.';
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateList(
        RichNodeType $type,
        array $node,
        string $pointer,
        array &$errors,
    ): void {
        $items = $node['items'] ?? null;

        if (! is_array($items) || $items === []) {
            $errors["{$pointer}/items"][] = 'The list must contain at least one item.';

            return;
        }

        foreach ($items as $index => $item) {
            $this->mergeErrors(
                $errors,
                $this->segmentErrors($item, "{$pointer}/items/{$index}"),
            );
        }

        if ($type === RichNodeType::OrderedList
            && isset($node['start'])
            && (! is_int($node['start']) || $node['start'] < 1)) {
            $errors["{$pointer}/start"][] = 'The ordered-list start must be a positive integer.';
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateImage(
        array $node,
        string $pointer,
        array &$errors,
        bool $validateMediaCatalog,
    ): void {
        $hasMedia = is_int($node['media_id'] ?? null) && $node['media_id'] > 0;
        $hasSource = $this->isSafeMediaUrl($node['src'] ?? null);

        if (! $hasMedia && ! $hasSource) {
            $errors["{$pointer}/src"][] = 'The image needs a media reference or safe source URL.';
        }

        if ($hasMedia && $validateMediaCatalog && $this->mediaCatalogAvailable()) {
            $record = Media::query()->find($node['media_id']);

            // This install's catalog answers first; the versioned media index
            // answers when it has nothing, so a library hosted elsewhere still
            // validates. Matches how block props resolve the same reference.
            if ($record === null) {
                if (app(MediaIndex::class)->find($node['media_id']) === null) {
                    $errors["{$pointer}/media_id"][] = 'The selected media item must be an image.';
                }
            } elseif ($record->kind !== MediaKind::Image) {
                $errors["{$pointer}/media_id"][] = 'The selected media item must be an image.';
            }
        }

        if (! is_string($node['alt'] ?? null) || trim($node['alt']) === '') {
            $errors["{$pointer}/alt"][] = 'The image alt text is required.';
        }

        foreach (['width', 'height'] as $dimension) {
            if (! is_int($node[$dimension] ?? null) || $node[$dimension] < 1) {
                $errors["{$pointer}/{$dimension}"][] = "The image {$dimension} must be a positive integer.";
            }
        }

        if (isset($node['layout'])
            && ! in_array($node['layout'], self::IMAGE_LAYOUTS, true)) {
            $errors["{$pointer}/layout"][] = 'The selected image layout is invalid.';
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

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateCallout(array $node, string $pointer, array &$errors): void
    {
        if (! in_array($node['tone'] ?? null, self::CALLOUT_TONES, true)) {
            $errors["{$pointer}/tone"][] = 'The selected callout tone is invalid.';
        }

        if (! is_string($node['title'] ?? null) || trim($node['title']) === '') {
            $errors["{$pointer}/title"][] = 'The callout title is required.';
        }

        $this->mergeErrors(
            $errors,
            $this->segmentErrors($node['segments'] ?? null, "{$pointer}/segments"),
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateQuote(array $node, string $pointer, array &$errors): void
    {
        if (! is_string($node['quote'] ?? null) || trim($node['quote']) === '') {
            $errors["{$pointer}/quote"][] = 'The quote text is required.';
        }

        if (isset($node['source_url']) && ! $this->isSafeUrl($node['source_url'])) {
            $errors["{$pointer}/source_url"][] = 'The quote source URL is unsafe.';
        }

        foreach (['attribution', 'role'] as $field) {
            if (array_key_exists($field, $node)
                && $node[$field] !== null
                && (! is_string($node[$field]) || trim($node[$field]) === '')) {
                $errors["{$pointer}/{$field}"][] = "The quote {$field} must be non-empty text.";
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function segmentErrors(mixed $segments, string $pointer): array
    {
        if (! is_array($segments) || $segments === []) {
            return [$pointer => ['At least one text segment is required.']];
        }

        $errors = [];

        foreach ($segments as $index => $segment) {
            $segmentPointer = "{$pointer}/{$index}";

            if (! is_array($segment) || ! is_string($segment['text'] ?? null)) {
                $errors[$segmentPointer][] = 'The segment text must be a string.';

                continue;
            }

            if (trim($segment['text']) === '') {
                $errors["{$segmentPointer}/text"][] = 'The segment text must contain a visible character.';
            }

            if (isset($segment['mark'])
                && ! in_array($segment['mark'], self::MARKS, true)) {
                $errors["{$segmentPointer}/mark"][] = 'The selected text mark is invalid.';
            }

            if (isset($segment['href']) && ! $this->isSafeUrl($segment['href'])) {
                $errors["{$segmentPointer}/href"][] = 'The segment link is unsafe.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $values, array $keys): array
    {
        return array_intersect_key($values, array_flip($keys));
    }

    /**
     * @param  array<string, array<int, string>>  $target
     * @param  array<string, array<int, string>>  $incoming
     */
    private function mergeErrors(array &$target, array $incoming): void
    {
        $target = array_merge_recursive($target, $incoming);
    }
}
