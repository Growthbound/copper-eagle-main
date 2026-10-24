<?php

namespace App\Livewire\Admin;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Content\PublicLinkTarget;
use App\Content\RichContent;
use App\Content\SiteStructureRepository;
use App\Content\Validation\ContentValidationResult;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Navigation and footer')]
class NavigationFooterEditor extends Component
{
    /** @var array<int, array{label: string, href: string}> */
    public array $navigationLinks = [];

    /** @var array<int, array{label: string, href: string}> */
    public array $companyLinks = [];

    /** @var array<int, array{label: string, href: string}> */
    public array $resourceLinks = [];

    #[Locked]
    public ?int $navigationLockVersion = null;

    #[Locked]
    public ?int $footerLockVersion = null;

    public function mount(SiteStructureRepository $structure): void
    {
        $this->navigationLinks = $structure->navigation();
        $footer = $structure->footer();
        $this->companyLinks = $footer['company'];
        $this->resourceLinks = $footer['resources'];
        $this->navigationLockVersion = $this->lockVersion(
            ContentKind::Navigation,
            'primary',
        );
        $this->footerLockVersion = $this->lockVersion(
            ContentKind::Footer,
            'site-footer',
        );
    }

    public function addLink(string $collection): void
    {
        if (! in_array($collection, ['navigationLinks', 'companyLinks', 'resourceLinks'], true)) {
            return;
        }

        $this->{$collection}[] = ['label' => '', 'href' => ''];
    }

    public function removeLink(string $collection, int $index): void
    {
        if (! in_array($collection, ['navigationLinks', 'companyLinks', 'resourceLinks'], true)
            || ! isset($this->{$collection}[$index])) {
            return;
        }

        array_splice($this->{$collection}, $index, 1);
    }

    public function save(
        RichContent $links,
        PublicLinkTarget $publicLinks,
        ContentLifecycle $lifecycle,
        SiteStructureRepository $structure,
    ): void {
        $this->resetErrorBag('links');

        $collections = [
            'navigation' => $this->navigationLinks,
            'company' => $this->companyLinks,
            'resources' => $this->resourceLinks,
        ];

        foreach ($this->linkErrors($collections, $links, $publicLinks) as $error) {
            $this->addError('links', $error);
        }

        if ($this->getErrorBag()->has('links')) {
            return;
        }

        try {
            [$navigationLockVersion, $footerLockVersion] = DB::transaction(
                function () use (
                    $collections,
                    $lifecycle,
                    $links,
                    $publicLinks,
                    $structure,
                ): array {
                    $structure->lockPublicationGraph();
                    $errors = $this->linkErrors(
                        $collections,
                        $links,
                        $publicLinks,
                    );

                    if ($errors !== []) {
                        throw new ContentValidationFailed(
                            new ContentValidationResult([
                                '/links' => $errors,
                            ]),
                        );
                    }

                    $navigationLockVersion = $this->persist(
                        $lifecycle,
                        ContentKind::Navigation,
                        'primary',
                        'Primary navigation',
                        ['items' => $this->clean($this->navigationLinks)],
                        $this->navigationLockVersion,
                    );
                    $footerLockVersion = $this->persist(
                        $lifecycle,
                        ContentKind::Footer,
                        'site-footer',
                        'Site footer',
                        [
                            'company' => $this->clean($this->companyLinks),
                            'resources' => $this->clean($this->resourceLinks),
                        ],
                        $this->footerLockVersion,
                    );

                    return [$navigationLockVersion, $footerLockVersion];
                },
            );
            $this->navigationLockVersion = $navigationLockVersion;
            $this->footerLockVersion = $footerLockVersion;
            session()->flash('status', 'Navigation and footer published.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->addError('links', collect($failure->result->errors())->flatten()->implode(' '));
        }
    }

    /**
     * @param  array<string, array<int, array{label?: mixed, href?: mixed}>>  $collections
     * @return array<int, string>
     */
    private function linkErrors(
        array $collections,
        RichContent $links,
        PublicLinkTarget $publicLinks,
    ): array {
        $errors = [];

        foreach ($collections as $name => $items) {
            foreach ($items as $index => $item) {
                if (! is_array($item)
                    || ! is_string($item['label'] ?? null)
                    || trim($item['label']) === ''
                    || ! $links->isSafeUrl($item['href'] ?? null)
                    || ! $publicLinks->exists($item['href'] ?? null)) {
                    $errors[] = "The {$name} link at position ".($index + 1).' needs a label and an existing public destination.';
                }
            }
        }

        return $errors;
    }

    public function render(): View
    {
        return view('livewire.admin.navigation-footer-editor');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(
        ContentLifecycle $lifecycle,
        ContentKind $kind,
        string $slug,
        string $title,
        array $data,
        ?int $expectedLockVersion,
    ): int {
        $identity = [
            'kind' => $kind,
            'slug' => $slug,
            'title' => $title,
            'template' => PageTemplate::Standard,
        ];
        $payload = [
            'title' => $title,
            'blocks' => [],
            ...$data,
        ];
        $entry = ContentEntry::query()
            ->where('kind', $kind->value)
            ->where('slug', $slug)
            ->first();

        if ($entry === null) {
            if ($expectedLockVersion !== null) {
                throw new ContentConflict($expectedLockVersion, 0);
            }

            $entry = $lifecycle->createDraft($identity, $payload, $this->owner());
            $revision = $entry->revisions()->sole();
        } else {
            if ($expectedLockVersion === null) {
                throw new ContentConflict(0, $entry->lock_version);
            }

            $revision = $lifecycle->saveRevision(
                $entry,
                $payload,
                $expectedLockVersion,
                $this->owner(),
                ContentSource::Admin,
                summary: 'Owner updated site structure',
                identity: $identity,
            );
            $entry->refresh();
        }

        return $lifecycle->publish(
            $entry,
            $revision,
            $entry->lock_version,
            $this->owner(),
        )->lock_version;
    }

    private function lockVersion(ContentKind $kind, string $slug): ?int
    {
        return ContentEntry::query()
            ->where('kind', $kind->value)
            ->where('slug', $slug)
            ->value('lock_version');
    }

    /**
     * @param  array<int, array{label: string, href: string}>  $items
     * @return array<int, array{label: string, href: string}>
     */
    private function clean(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'label' => trim($item['label']),
                'href' => trim($item['href']),
            ])
            ->values()
            ->all();
    }

    private function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
