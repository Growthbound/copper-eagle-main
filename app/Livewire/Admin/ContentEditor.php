<?php

namespace App\Livewire\Admin;

use App\Content\BlockRegistry;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Content\PageTemplateRegistry;
use App\Enums\MediaKind;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Content editor')]
class ContentEditor extends Component
{
    public int $entryId;

    public string $title = '';

    public string $slug = '';

    public string $kind = 'page';

    public string $template = 'standard';

    /** @var array<string, mixed> */
    public array $payload = [];

    public int $lockVersion = 0;

    public int $baseRevisionId;

    public string $summary = '';

    public string $scheduledFor = '';

    public string $selectedBlockType = 'hero/interior';

    public ?string $previewUrl = null;

    /** @var array<string, array<int, string>> */
    public array $validationMessages = [];

    /** @var array<string, string> */
    public array $jsonFields = [];

    /** @var array<string, int|string|null> */
    public array $selectedMedia = [];

    public function mount(ContentEntry $entry): void
    {
        $entry->load('revisions');
        $revision = $entry->revisions->sortByDesc('revision_number')->firstOrFail();
        $this->entryId = $entry->id;
        $this->syncIdentity($revision, $entry);
        $this->payload = $revision->payload;
        $this->lockVersion = $entry->lock_version;
        $this->baseRevisionId = $revision->id;
        $this->scheduledFor = $entry->scheduled_for
            ?->setTimezone($this->siteTimezone())
            ->format('Y-m-d\TH:i') ?? '';
        $this->primeJsonFields();
    }

    public function addBlock(string $type, BlockRegistry $registry): void
    {
        $definition = $registry->definition($type);

        if ($definition === null || $this->template !== PageTemplate::Standard->value) {
            $this->addError('block', 'Select an allowed block for a standard page.');

            return;
        }

        $props = [];

        foreach ($definition->fields as $field => $schema) {
            if (! ($schema['required'] ?? false)) {
                continue;
            }

            $props[$field] = ($schema['type'] ?? 'string') === 'array' ? [] : '';
        }

        $this->payload['blocks'] ??= [];
        $this->payload['blocks'][] = ['type' => $type, 'props' => $props];
        $this->primeJsonFields();
    }

    public function addSelectedBlock(BlockRegistry $registry): void
    {
        $this->addBlock($this->selectedBlockType, $registry);
    }

    public function removeBlock(int $index): void
    {
        if (! isset($this->payload['blocks'][$index])) {
            return;
        }

        array_splice($this->payload['blocks'], $index, 1);
        $this->primeJsonFields();
    }

    public function moveBlock(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);
        $blocks = $this->payload['blocks'] ?? [];

        if (! isset($blocks[$index], $blocks[$target])) {
            return;
        }

        [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];
        $this->payload['blocks'] = array_values($blocks);
        $this->primeJsonFields();
    }

    public function applyJsonField(string $key): void
    {
        $path = $this->jsonPath($key);

        if ($path === null) {
            $this->addError('jsonFields.'.$key, 'This structured field no longer exists.');

            return;
        }

        $decoded = json_decode($this->jsonFields[$key] ?? '', true);

        if (! is_array($decoded)) {
            $this->addError('jsonFields.'.$key, 'Enter a valid JSON array or object.');

            return;
        }

        data_set($this->payload, $path, $decoded);
        $this->resetErrorBag('jsonFields.'.$key);
    }

    public function addRichNode(string $path, string $type = 'paragraph'): void
    {
        $nodes = data_get($this->payload, $path, []);

        if (! is_array($nodes)) {
            $nodes = [];
        }

        $nodes[] = match ($type) {
            'heading' => ['type' => 'heading', 'level' => 3, 'text' => 'New section'],
            'unordered-list' => ['type' => 'unordered-list', 'items' => [[['text' => 'New item']]]],
            'ordered-list' => ['type' => 'ordered-list', 'items' => [[['text' => 'New step']]]],
            'callout' => ['type' => 'callout', 'tone' => 'note', 'title' => 'Helpful note', 'segments' => [['text' => 'Add the note.']]],
            'quote' => ['type' => 'quote', 'quote' => 'Add the verified quote.', 'attribution' => 'Attribution'],
            'divider' => ['type' => 'divider'],
            default => ['type' => 'paragraph', 'segments' => [['text' => 'Add the paragraph.']]],
        };

        data_set($this->payload, $path, $nodes);
        $this->primeJsonFields();
    }

    public function insertMedia(string $key): void
    {
        $path = $this->jsonPath($key);
        $mediaId = filter_var($this->selectedMedia[$key] ?? null, FILTER_VALIDATE_INT);

        if ($path === null || $mediaId === false) {
            $this->addError('selectedMedia.'.$key, 'Select a media item for this field.');

            return;
        }

        $media = Media::query()->findOrFail($mediaId);
        $field = str($path)->afterLast('.')->toString();

        if ($field === 'video' && $media->kind !== MediaKind::Video) {
            $this->addError(
                'selectedMedia.'.$key,
                'Choose a video media item for this field.',
            );

            return;
        }

        $hasVerifiedSource = is_string($media->source_url)
            && trim($media->source_url) !== '';
        $reference = $field === 'video'
            ? [
                'src' => $media->url(),
                'type' => $media->mime_type,
            ]
            : array_filter([
                'media_id' => $media->id,
                'src' => $media->url(),
                'alt' => $media->alt ?: $media->title,
                'width' => $media->width,
                'height' => $media->height,
                'source_label' => $hasVerifiedSource
                    ? ($media->title ?: $media->original_name)
                    : null,
                'source_url' => $hasVerifiedSource ? $media->source_url : null,
            ], fn (mixed $value): bool => $value !== null);
        $current = data_get($this->payload, $path, []);

        if ($field === 'images' || (is_array($current) && array_is_list($current) && $current !== [])) {
            $current[] = $reference;
            data_set($this->payload, $path, array_values($current));
        } else {
            data_set($this->payload, $path, $reference);
        }

        $this->selectedMedia[$key] = null;
        $this->primeJsonFields();
        $this->resetErrorBag('selectedMedia.'.$key);
    }

    public function removeRichNode(string $path, int $index): void
    {
        $nodes = data_get($this->payload, $path, []);

        if (! is_array($nodes) || ! isset($nodes[$index])) {
            return;
        }

        array_splice($nodes, $index, 1);
        data_set($this->payload, $path, $nodes);
        $this->primeJsonFields();
    }

    public function save(ContentLifecycle $lifecycle): void
    {
        $identity = $this->validatedIdentity();

        if ($identity === null) {
            return;
        }

        try {
            $revision = $lifecycle->saveRevision(
                $this->entry(),
                $this->payload,
                $this->lockVersion,
                $this->owner(),
                ContentSource::Admin,
                summary: $this->summary,
                identity: $identity,
            );
            $this->syncAfterMutation($revision);
            $this->summary = '';
            session()->flash('status', 'Draft revision saved.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function validateContent(ContentLifecycle $lifecycle): void
    {
        $identity = $this->validatedIdentity();

        if ($identity === null) {
            return;
        }

        try {
            $result = $lifecycle->validateCandidate(
                $this->entry(),
                $identity,
                $this->payload,
                $this->lockVersion,
                $this->owner(),
            );
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());

            return;
        }

        $this->validationMessages = [
            ...$result->errors(),
            ...$result->warnings(),
        ];

        if ($result->hasErrors()) {
            $this->addError('payload', 'Resolve the listed content errors before publication.');
        } else {
            $this->resetErrorBag('payload');
            session()->flash('status', 'Content contract is valid.');
        }
    }

    public function markScaffoldReplaced(): void
    {
        if (($this->payload['_scaffold'] ?? null) !== true) {
            return;
        }

        unset($this->payload['_scaffold']);
        $this->validationMessages = [];
        $this->resetErrorBag('payload');
        session()->flash(
            'status',
            'Scaffold marker removed. Save a new revision before validation or publication.',
        );
    }

    public function preview(ContentLifecycle $lifecycle): void
    {
        try {
            $issued = $lifecycle->issuePreview(
                $this->entry(),
                $this->baseRevision(),
                $this->lockVersion,
                $this->owner(),
            );
            $this->previewUrl = $issued->url();
            session()->flash('status', 'Private preview issued.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function publish(ContentLifecycle $lifecycle): void
    {
        try {
            $entry = $lifecycle->publish(
                $this->entry(),
                $this->baseRevision(),
                $this->lockVersion,
                $this->owner(),
            );
            $this->syncEntry($entry);
            session()->flash('status', 'Revision published.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function schedule(ContentLifecycle $lifecycle): void
    {
        $this->validate(['scheduledFor' => ['required', 'date_format:Y-m-d\TH:i']]);
        $scheduled = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $this->scheduledFor,
            $this->siteTimezone(),
        );

        if ($scheduled->format('Y-m-d\TH:i') !== $this->scheduledFor) {
            $this->addError(
                'scheduledFor',
                'That local time does not exist in the configured time zone.',
            );

            return;
        }

        try {
            $entry = $lifecycle->schedule(
                $this->entry(),
                $this->baseRevision(),
                $scheduled->utc(),
                $this->lockVersion,
                $this->owner(),
            );
            $this->syncEntry($entry);
            session()->flash('status', 'Publication scheduled.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function cancelSchedule(ContentLifecycle $lifecycle): void
    {
        try {
            $entry = $lifecycle->cancelSchedule(
                $this->entry(),
                $this->lockVersion,
                $this->owner(),
            );
            $this->syncEntry($entry);
            session()->flash('status', 'Scheduled publication cancelled.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function archive(ContentLifecycle $lifecycle): void
    {
        try {
            $this->syncEntry($lifecycle->archive(
                $this->entry(),
                $this->lockVersion,
                $this->owner(),
            ));
            session()->flash('status', 'Content archived.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->applyValidationFailure($failure);
        }
    }

    public function restore(ContentLifecycle $lifecycle): void
    {
        try {
            $this->syncEntry($lifecycle->restore(
                $this->entry(),
                $this->lockVersion,
                $this->owner(),
            ));
            session()->flash('status', 'Content restored to draft.');
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        }
    }

    public function render(
        BlockRegistry $blocks,
        PageTemplateRegistry $templates,
    ): View {
        $entry = $this->entry()->load(['publishedRevision', 'scheduledRevision']);

        return view('livewire.admin.content-editor', [
            'entry' => $entry,
            'blocks' => $blocks->all(),
            'templateDefinition' => $templates->definition(PageTemplate::from($this->template)),
            'media' => Media::query()->latest()->limit(100)->get(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatedIdentity(): ?array
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:\\/|[a-z0-9]+(?:[\\/-][a-z0-9]+)*)$/',
                Rule::unique('content_entries', 'slug')
                    ->where('kind', $this->kind)
                    ->ignore($this->entryId),
            ],
            'kind' => ['required', Rule::enum(ContentKind::class)],
            'template' => ['required', Rule::enum(PageTemplate::class)],
        ]);

        return [
            'kind' => ContentKind::from($validated['kind']),
            'slug' => $validated['slug'],
            'title' => trim($validated['title']),
            'template' => PageTemplate::from($validated['template']),
        ];
    }

    private function entry(): ContentEntry
    {
        return ContentEntry::query()->findOrFail($this->entryId);
    }

    private function baseRevision(): ContentRevision
    {
        return $this->entry()->revisions()->findOrFail($this->baseRevisionId);
    }

    private function syncAfterMutation(ContentRevision $revision): void
    {
        $this->baseRevisionId = $revision->id;
        $this->payload = $revision->payload;
        $this->syncEntry($revision->entry()->firstOrFail());
        $this->syncIdentity($revision);
        $this->primeJsonFields();
    }

    private function syncEntry(ContentEntry $entry): void
    {
        $entry->refresh();
        $this->lockVersion = $entry->lock_version;
        $this->scheduledFor = $entry->scheduled_for
            ?->setTimezone($this->siteTimezone())
            ->format('Y-m-d\TH:i') ?? '';
    }

    private function syncIdentity(
        ContentRevision $revision,
        ?ContentEntry $fallback = null,
    ): void {
        $identity = is_array($revision->identity) ? $revision->identity : [];
        $fallback ??= $revision->entry()->firstOrFail();
        $this->title = (string) ($identity['title'] ?? $fallback->title);
        $this->slug = (string) ($identity['slug'] ?? $fallback->slug);
        $this->kind = $identity['kind'] instanceof ContentKind
            ? $identity['kind']->value
            : (string) ($identity['kind'] ?? $fallback->kind->value);
        $this->template = $identity['template'] instanceof PageTemplate
            ? $identity['template']->value
            : (string) ($identity['template'] ?? $fallback->template->value);
    }

    private function siteTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private function applyValidationFailure(ContentValidationFailed $failure): void
    {
        $this->validationMessages = $failure->result->errors();
        $this->addError('payload', 'Resolve the listed content errors before continuing.');
    }

    private function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function primeJsonFields(): void
    {
        $this->jsonFields = [];

        foreach ($this->arrayFieldPaths($this->payload) as $path => $default) {
            $key = hash('sha256', $path);
            $value = data_get($this->payload, $path, $default);
            $this->jsonFields[$key] = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) ?: json_encode($default);
            session()->put('content-editor.json-paths.'.$this->entryId.'.'.$key, $path);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function arrayFieldPaths(array $payload): array
    {
        $paths = [];
        $registry = app(BlockRegistry::class);

        foreach ($payload['blocks'] ?? [] as $index => $block) {
            $definition = is_string($block['type'] ?? null)
                ? $registry->definition($block['type'])
                : null;

            foreach ($definition?->fields ?? [] as $field => $schema) {
                if (($schema['type'] ?? null) === 'array') {
                    $paths["blocks.{$index}.props.{$field}"] = [];
                }
            }

            foreach (($block['props'] ?? []) as $field => $value) {
                if (is_array($value)) {
                    $paths["blocks.{$index}.props.{$field}"] = [];
                }
            }
        }

        $template = PageTemplate::tryFrom($this->template);
        $sectionTypes = $template === null
            ? []
            : app(PageTemplateRegistry::class)->definition($template)->sectionTypes;

        foreach ($payload['sections'] ?? [] as $section => $props) {
            $definition = isset($sectionTypes[$section])
                ? $registry->definition($sectionTypes[$section])
                : null;

            foreach ($definition?->fields ?? [] as $field => $schema) {
                if (($schema['type'] ?? null) === 'array') {
                    $paths["sections.{$section}.{$field}"] = [];
                }
            }

            foreach ($props as $field => $value) {
                if (is_array($value)) {
                    $paths["sections.{$section}.{$field}"] = [];
                }
            }
        }

        return $paths;
    }

    private function jsonPath(string $key): ?string
    {
        $path = session()->get('content-editor.json-paths.'.$this->entryId.'.'.$key);

        return is_string($path) ? $path : null;
    }
}
