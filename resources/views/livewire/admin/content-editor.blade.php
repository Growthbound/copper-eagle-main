<div>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.content.index') }}" wire:navigate class="text-sm font-semibold text-text-muted underline">← Content</a>
            <h1 class="mt-3 font-heading text-3xl font-bold tracking-tight">{{ $title }}</h1>
            <div class="mt-2 flex flex-wrap gap-2">
                <span class="admin-pill bg-primary/10 text-primary">{{ $entry->status->value }}</span>
                <span class="admin-pill bg-surface text-text-muted">Lock {{ $lockVersion }}</span>
                <span class="admin-pill bg-surface text-text-muted">Revision {{ $entry->revisions()->max('revision_number') }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="admin-button admin-button-quiet" href="{{ route('admin.content.history', $entry) }}" wire:navigate>History</a>
            @if ($entry->published_revision_id)
                <a class="admin-button admin-button-quiet" href="{{ url($entry->slug) }}">View live</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif
    @error('conflict') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror
    @error('payload') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror

    @if (($payload['_scaffold'] ?? null) === true)
        <section class="admin-card mb-6 border border-accent/40 bg-accent/5" aria-labelledby="scaffold-warning">
            <h2 id="scaffold-warning" class="font-heading text-lg font-bold">Draft scaffold is not publication-ready</h2>
            <p class="mt-2 text-sm text-text-muted">
                Replace every generated name, claim, price, review, statistic, location, source, and media reference.
                Preview, publishing, and scheduling remain blocked while this marker is present.
            </p>
            <button
                type="button"
                class="admin-button admin-button-quiet mt-4"
                wire:click="markScaffoldReplaced"
                wire:confirm="I replaced every scaffold value with verified business content. Remove the publication block?"
            >
                Mark scaffold content as replaced
            </button>
        </section>
    @endif

    @if ($validationMessages !== [])
        <section class="admin-card mb-6" aria-labelledby="contract-messages">
            <h2 id="contract-messages" class="font-heading text-lg font-bold">Content contract</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($validationMessages as $pointer => $messages)
                    @foreach ($messages as $message)
                        <li><code class="font-mono text-xs">{{ $pointer }}</code> — {{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        </section>
    @endif

    <section class="admin-card mb-6">
        <h2 class="mb-4 font-heading text-xl font-bold">Identity and metadata</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="admin-label" for="editor-title">Title</label>
                <input id="editor-title" class="admin-input" wire:model="title">
                @error('title') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="editor-slug">Slug</label>
                <input id="editor-slug" class="admin-input font-mono" wire:model="slug">
                @error('slug') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="editor-kind">Kind</label>
                <select id="editor-kind" class="admin-input" wire:model="kind">
                    @foreach (App\Content\Enums\ContentKind::supportedCases() as $option)
                        <option value="{{ $option->value }}">{{ str($option->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="editor-template">Template</label>
                <select id="editor-template" class="admin-input" wire:model.live="template">
                    @foreach (App\Content\Enums\PageTemplate::cases() as $option)
                        <option value="{{ $option->value }}">{{ str($option->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="admin-label" for="payload-title">Payload title</label>
                <input id="payload-title" class="admin-input" wire:model.blur="payload.title">
            </div>
            <div class="md:col-span-2">
                <label class="admin-label" for="payload-description">Meta description</label>
                <textarea id="payload-description" class="admin-input min-h-24" wire:model.blur="payload.description"></textarea>
            </div>
            <label class="flex items-center gap-3 text-sm font-semibold">
                <input type="checkbox" wire:model="payload.noindex">
                Keep this content out of search results
            </label>
            <div>
                <label class="admin-label" for="revision-summary">Revision note</label>
                <input id="revision-summary" class="admin-input" wire:model="summary" placeholder="What changed and why">
            </div>
        </div>
    </section>

    @if ($template === App\Content\Enums\PageTemplate::Standard->value)
        <section class="mb-6">
            <div class="admin-card mb-4 flex flex-wrap items-end gap-3">
                <div class="min-w-64 flex-1">
                    <label class="admin-label" for="new-block">Add approved block</label>
                    <select id="new-block" class="admin-input" wire:model="selectedBlockType">
                        @foreach ($blocks as $definition)
                            <option value="{{ $definition->type }}">{{ $definition->type }} — {{ $definition->purpose }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="admin-button" wire:click="addSelectedBlock">Add block</button>
            </div>

            <div class="space-y-4">
                @forelse ($payload['blocks'] ?? [] as $index => $block)
                    @php
                        $definition = $blocks[$block['type']] ?? null;
                        $props = $block['props'] ?? [];
                        $fields = collect($definition?->fields ?? [])
                            ->union(collect($props)->map(fn ($value) => ['type' => is_array($value) ? 'array' : 'string']))
                            ->all();
                    @endphp
                    <article class="admin-card" wire:key="editor-block-{{ $index }}-{{ $block['type'] }}">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs text-text-muted">{{ $block['type'] }}</p>
                                <h2 class="font-heading text-xl font-bold">{{ str($block['type'])->after('/')->headline() }}</h2>
                                <p class="mt-1 text-sm text-text-muted">{{ $definition?->purpose }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="admin-button admin-button-quiet" wire:click="moveBlock({{ $index }}, -1)">↑</button>
                                <button type="button" class="admin-button admin-button-quiet" wire:click="moveBlock({{ $index }}, 1)">↓</button>
                                <button type="button" class="admin-button admin-button-quiet text-danger" wire:click="removeBlock({{ $index }})">Remove</button>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($fields as $field => $schema)
                                @php
                                    $fieldPath = 'payload.blocks.'.$index.'.props.'.$field;
                                @endphp
                                @include('livewire.admin.partials.field-editor', [
                                    'path' => $fieldPath,
                                    'field' => $field,
                                    'schema' => $schema,
                                    'value' => data_get($payload, 'blocks.'.$index.'.props.'.$field),
                                ])
                                @if ($field === 'nodes')
                                    @include('livewire.admin.partials.rich-node-editor', [
                                        'path' => "blocks.{$index}.props.nodes",
                                        'nodes' => data_get($payload, "blocks.{$index}.props.nodes", []),
                                    ])
                                @endif
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="admin-card text-center text-sm text-text-muted">No blocks yet. Add the hero first, then build the page in reading order.</div>
                @endforelse
            </div>
        </section>
    @else
        <section class="mb-6 space-y-4">
            <div class="admin-card">
                <h2 class="font-heading text-xl font-bold">{{ str($template)->headline() }} fixed sequence</h2>
                <p class="mt-2 text-sm text-text-muted">Section order and component types are locked. Edit only the governed content fields.</p>
            </div>
            @foreach ($templateDefinition->sequence as $section)
                @php
                    $type = $templateDefinition->sectionTypes[$section];
                    $definition = $blocks[$type];
                    $props = data_get($payload, 'sections.'.$section, []);
                    $fields = collect($definition->fields)
                        ->union(collect($props)->map(fn ($value) => ['type' => is_array($value) ? 'array' : 'string']))
                        ->all();
                @endphp
                <article class="admin-card" wire:key="fixed-section-{{ $section }}">
                    <p class="font-mono text-xs text-text-muted">{{ $section }} · {{ $type }}</p>
                    <h2 class="mt-1 font-heading text-xl font-bold">{{ str($section)->headline() }}</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($fields as $field => $schema)
                            @php
                                $fieldPath = 'payload.sections.'.$section.'.'.$field;
                            @endphp
                            @include('livewire.admin.partials.field-editor', [
                                'path' => $fieldPath,
                                'field' => $field,
                                'schema' => $schema,
                                'value' => data_get($payload, "sections.{$section}.{$field}"),
                            ])
                            @if ($field === 'nodes')
                                @include('livewire.admin.partials.rich-node-editor', [
                                    'path' => "sections.{$section}.nodes",
                                    'nodes' => data_get($payload, "sections.{$section}.nodes", []),
                                ])
                            @endif
                        @endforeach
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="admin-card mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <button type="button" class="admin-button" wire:click="save">Save revision</button>
            <button type="button" class="admin-button admin-button-quiet" wire:click="validateContent">Validate</button>
            <button type="button" class="admin-button admin-button-quiet" wire:click="preview">Issue preview</button>
            <button type="button" class="admin-button admin-button-quiet" wire:click="publish" wire:confirm="Publish this exact revision now?">Publish</button>
            @if ($entry->status === App\Content\Enums\ContentStatus::Archived)
                <button type="button" class="admin-button admin-button-quiet" wire:click="restore">Restore draft</button>
            @else
                <button type="button" class="admin-button admin-button-quiet text-danger" wire:click="archive" wire:confirm="Archive this content and remove its managed publication?">Archive</button>
            @endif
            @if ($previewUrl)
                <a class="admin-button admin-button-quiet" href="{{ $previewUrl }}" target="_blank" rel="noopener">Open private preview</a>
            @endif
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
            <div>
                <label class="admin-label" for="scheduled-for">Schedule exact revision</label>
                <input id="scheduled-for" type="datetime-local" class="admin-input" wire:model="scheduledFor">
                <p class="admin-help">Time zone: {{ config('app.timezone', 'UTC') }}</p>
                @error('scheduledFor') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <button type="button" class="admin-button" wire:click="schedule" wire:confirm="Schedule this exact revision?">Schedule</button>
            @if ($entry->status === App\Content\Enums\ContentStatus::Scheduled)
                <button type="button" class="admin-button admin-button-quiet" wire:click="cancelSchedule">Cancel schedule</button>
            @endif
        </div>
    </section>

    <aside class="admin-card">
        <h2 class="font-heading text-lg font-bold">Approved media references</h2>
        <p class="mt-1 text-sm text-text-muted">Use these IDs in structured <code>media_id</code> fields. Media remains centrally managed.</p>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($media as $item)
                <div class="rounded-lg bg-surface px-3 py-2 text-sm">
                    <strong>#{{ $item->id }}</strong> {{ $item->title ?: $item->original_name }}
                </div>
            @empty
                <p class="text-sm text-text-muted">No media uploaded yet.</p>
            @endforelse
        </div>
    </aside>
</div>
