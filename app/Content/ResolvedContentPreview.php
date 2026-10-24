<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Models\ContentEntry;
use App\Models\ContentRevision;

final readonly class ResolvedContentPreview
{
    public function __construct(
        public ContentEntry $entry,
        public ContentRevision $revision,
    ) {}

    public function kind(): ContentKind
    {
        $kind = $this->revision->identity['kind'] ?? null;

        if ($kind instanceof ContentKind) {
            return $kind;
        }

        return is_string($kind)
            ? ContentKind::tryFrom($kind) ?? $this->entry->kind
            : $this->entry->kind;
    }

    public function page(): ?ManagedPage
    {
        return match ($this->kind()) {
            ContentKind::Page,
            ContentKind::Service,
            ContentKind::Location,
            ContentKind::Article => ManagedPage::fromRevision($this->entry, $this->revision),
            ContentKind::Navigation,
            ContentKind::Footer,
            ContentKind::Faq,
            ContentKind::Testimonial,
            ContentKind::Project,
            ContentKind::TeamMember => null,
        };
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return is_array($this->revision->payload) ? $this->revision->payload : [];
    }

    public function title(): string
    {
        $payload = $this->payload();

        return trim((string) ($payload['title'] ?? $this->entry->title));
    }

    public function description(): ?string
    {
        $description = $this->payload()['description'] ?? null;

        return is_string($description) && trim($description) !== ''
            ? trim($description)
            : null;
    }

    /** @return array<int, array{label: string, href: string}> */
    public function navigation(): array
    {
        return $this->links($this->payload()['items'] ?? []);
    }

    /**
     * @return array{
     *     company: array<int, array{label: string, href: string}>,
     *     resources: array<int, array{label: string, href: string}>
     * }
     */
    public function footer(): array
    {
        $payload = $this->payload();

        return [
            'company' => $this->links($payload['company'] ?? []),
            'resources' => $this->links($payload['resources'] ?? []),
        ];
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function links(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item)
                && is_string($item['label'] ?? null)
                && is_string($item['href'] ?? null))
            ->map(fn (array $item): array => [
                'label' => trim($item['label']),
                'href' => $item['href'],
            ])
            ->values()
            ->all();
    }
}
