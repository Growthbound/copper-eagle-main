<?php

namespace App\Livewire\Admin;

use App\Content\ContentDraftScaffold;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentValidationFailed;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Content')]
class ContentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'kind', except: 'all')]
    public string $kindFilter = 'all';

    public string $newTitle = '';

    public string $newSlug = '';

    public string $newKind = 'page';

    public string $newTemplate = 'standard';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedKindFilter(): void
    {
        $this->resetPage();
    }

    public function create(
        ContentLifecycle $lifecycle,
        ContentDraftScaffold $scaffolds,
    ): void {
        $validated = $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newSlug' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:\\/|[a-z0-9]+(?:[\\/-][a-z0-9]+)*)$/',
                Rule::unique('content_entries', 'slug')->where('kind', $this->newKind),
            ],
            'newKind' => ['required', Rule::in([ContentKind::Page->value])],
            'newTemplate' => ['required', Rule::enum(PageTemplate::class)],
        ]);

        $template = PageTemplate::from($validated['newTemplate']);

        try {
            $entry = $lifecycle->createDraft(
                [
                    'kind' => ContentKind::from($validated['newKind']),
                    'slug' => $validated['newSlug'],
                    'title' => trim($validated['newTitle']),
                    'template' => $template,
                ],
                $scaffolds->for($template, trim($validated['newTitle'])),
                $this->owner(),
            );
        } catch (ContentValidationFailed $failure) {
            foreach ($failure->result->errors() as $pointer => $messages) {
                $field = match ($pointer) {
                    '/identity/title' => 'newTitle',
                    '/identity/kind' => 'newKind',
                    '/identity/template' => 'newTemplate',
                    default => 'newSlug',
                };

                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->redirectRoute('admin.content.edit', ['entry' => $entry], navigate: true);
    }

    public function render(): View
    {
        $entries = ContentEntry::query()
            ->with(['publishedRevision', 'scheduledRevision'])
            ->withCount('revisions')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);
                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', '%'.$term.'%')
                        ->orWhere('slug', 'like', '%'.$term.'%');
                });
            })
            ->when(
                ContentStatus::tryFrom($this->statusFilter) !== null,
                fn ($query) => $query->where('status', $this->statusFilter),
            )
            ->when(
                ContentKind::tryFrom($this->kindFilter) !== null,
                fn ($query) => $query->where('kind', $this->kindFilter),
            )
            ->latest('updated_at')
            ->paginate(20);

        return view('livewire.admin.content-index', [
            'entries' => $entries,
            'kinds' => ContentKind::supportedCases(),
            'creatableKinds' => [ContentKind::Page],
            'statuses' => ContentStatus::cases(),
            'templates' => PageTemplate::cases(),
        ]);
    }

    private function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
