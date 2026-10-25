<?php

namespace App\Content;

/**
 * A piece of structured content stored as a JSON file in the repository.
 *
 * Services and service areas are records rather than pages: one record feeds
 * several surfaces — a card, a dropdown option, its own page, a sitemap entry,
 * structured data — so the fact is written once and read everywhere.
 */
abstract class Record
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $data,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function name(): string
    {
        return (string) $this->get('name', $this->slug);
    }

    public function summary(): ?string
    {
        $summary = trim((string) $this->get('summary', ''));

        return $summary === '' ? null : $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function image(): ?array
    {
        $image = $this->get('image');

        return is_array($image)
            ? app(BlockData::class)->media($image)
            : null;
    }

    public function order(): int
    {
        return (int) $this->get('order', 999);
    }

    abstract public function url(): string;

    /**
     * A generated record URL, resolved through any fold the intent ledger has
     * recorded for it.
     *
     * The redirect exists for inbound links nobody controls. Our own navigation
     * has no reason to spend a hop, and linking to a URL we have decided to
     * retire keeps handing it the internal signal the fold is meant to move.
     */
    protected function consolidated(string $url): string
    {
        $target = app(UrlDispositionRegistry::class)->targetFor(
            (string) (parse_url($url, PHP_URL_PATH) ?: '/'),
        );

        return $target === null ? $url : rtrim(url('/'), '/').$target;
    }
}
