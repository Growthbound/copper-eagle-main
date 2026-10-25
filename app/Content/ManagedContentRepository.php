<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Models\ContentAuditEvent;
use App\Models\ContentEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final readonly class ManagedContentRepository
{
    public function __construct(
        private PageRepository $jsonPages,
        private ContentValidator $validator,
        private ManagedContentFileRepository $files,
    ) {}

    public function findPage(string $slug): Page|ManagedPage|null
    {
        $slug = $this->normalize($slug);

        if (! Schema::hasTable('content_entries')) {
            return $this->files->find($slug) ?? $this->jsonPages->find($slug);
        }

        $entry = ContentEntry::query()
            ->with('publishedRevision')
            ->whereIn('kind', $this->publicKinds())
            ->where('slug', $slug)
            ->first();

        if ($entry !== null
            && $entry->publishedRevision !== null
            && $entry->publishedRevision->content_entry_id === $entry->id
            && $this->validator->validate([
                'kind' => $entry->kind,
                'slug' => $entry->slug,
                'title' => $entry->title,
                'template' => $entry->template,
            ], $entry->publishedRevision->payload)->isValid()) {
            return ManagedPage::fromRevision($entry, $entry->publishedRevision);
        }

        if ($this->publishedPageSlugs()->contains($slug)) {
            return null;
        }

        return $this->files->find($slug) ?? $this->jsonPages->find($slug);
    }

    /**
     * @return Collection<int, ManagedPage>
     */
    public function publishedPages(): Collection
    {
        $database = Schema::hasTable('content_entries')
            ? ContentEntry::query()
                ->with('publishedRevision')
                ->whereIn('kind', $this->publicKinds())
                ->whereNotNull('published_revision_id')
                ->orderBy('slug')
                ->get()
                ->filter(fn (ContentEntry $entry): bool => $entry->publishedRevision !== null
                    && $entry->publishedRevision->content_entry_id === $entry->id
                    && $this->validator->validate([
                        'kind' => $entry->kind,
                        'slug' => $entry->slug,
                        'title' => $entry->title,
                        'template' => $entry->template,
                    ], $entry->publishedRevision->payload)->isValid())
                ->map(fn (ContentEntry $entry): ManagedPage => ManagedPage::fromRevision(
                    $entry,
                    $entry->publishedRevision,
                ))
                ->values()
            : collect();

        $claimed = $database->pluck('slug');

        return $database
            ->concat($this->files->all()->reject(
                fn (ManagedPage $page): bool => $claimed->contains($page->slug),
            ))
            ->sortBy(fn (ManagedPage $page): string => $page->slug)
            ->values();
    }

    /**
     * Public paths claimed by managed content stay claimed after archive so
     * the legacy JSON source cannot silently republish them.
     *
     * @return Collection<int, string>
     */
    public function claimedPageSlugs(): Collection
    {
        if (! Schema::hasTable('content_entries')) {
            return collect();
        }

        return $this->publishedPageSlugs();
    }

    /**
     * Every page identity that has reached publication remains claimed,
     * including identities from immutable revisions that are no longer the
     * entry's current slug.
     *
     * @return Collection<int, string>
     */
    private function publishedPageSlugs(): Collection
    {
        $historic = ContentAuditEvent::query()
            ->with(['entry:id,kind,slug', 'revision:id,identity'])
            ->whereIn('action', ['publish', 'rollback'])
            ->get()
            ->map(function (ContentAuditEvent $event): ?string {
                $identity = is_array($event->revision?->identity)
                    ? $event->revision->identity
                    : [];
                $kind = $identity['kind'] ?? $event->entry?->kind;
                $kind = $kind instanceof ContentKind ? $kind->value : $kind;

                if (! in_array($kind, $this->publicKinds(), true)) {
                    return null;
                }

                $slug = $identity['slug'] ?? $event->entry?->slug;

                return is_string($slug) ? $this->normalize($slug) : null;
            })
            ->filter();

        return $historic
            ->map(fn (string $slug): string => $this->normalize($slug))
            ->unique()
            ->sort()
            ->values();
    }

    private function normalize(string $slug): string
    {
        $normalized = trim($slug, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * @return Collection<int, ManagedPage>
     */
    public function publishedByKind(ContentKind $kind): Collection
    {
        return $this->publishedPages()
            ->filter(fn (ManagedPage $page): bool => $page->kind === $kind)
            ->sortByDesc(fn (ManagedPage $page): string => ($page->publishedAt() ?? '0000-00-00').'|'.$page->slug)
            ->values();
    }

    /**
     * @return list<string>
     */
    private function publicKinds(): array
    {
        return [
            ContentKind::Page->value,
            ContentKind::Service->value,
            ContentKind::Location->value,
            ContentKind::Article->value,
        ];
    }
}
