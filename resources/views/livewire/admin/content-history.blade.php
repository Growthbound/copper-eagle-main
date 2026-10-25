<div>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.content.edit', $entry) }}" wire:navigate class="text-sm font-semibold text-text-muted underline">← Editor</a>
            <h1 class="mt-3 font-heading text-3xl font-bold tracking-tight">{{ $entry->title }} history</h1>
            <p class="mt-2 text-sm text-text-muted">Immutable snapshots, pointer-level differences, and accountable publication events.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif
    @error('conflict') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror
    @error('rollback') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror

    <section class="admin-card mb-6">
        <h2 class="font-heading text-xl font-bold">Compare revisions</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="admin-label" for="from-revision">From</label>
                <select id="from-revision" class="admin-input" wire:model.live="fromRevisionId">
                    @foreach ($revisions as $revision)
                        <option value="{{ $revision->id }}">Revision {{ $revision->revision_number }} · {{ $revision->created_at?->format('M j, Y g:i A') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="to-revision">To</label>
                <select id="to-revision" class="admin-input" wire:model.live="toRevisionId">
                    @foreach ($revisions as $revision)
                        <option value="{{ $revision->id }}">Revision {{ $revision->revision_number }} · {{ $revision->created_at?->format('M j, Y g:i A') }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-2xl text-left text-sm">
                <caption class="sr-only">Changes between the selected revisions</caption>
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <th scope="col" class="px-3 py-2">JSON pointer</th>
                        <th scope="col" class="px-3 py-2">Before</th>
                        <th scope="col" class="px-3 py-2">After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($changes as $change)
                        <tr class="border-b border-border align-top">
                            <td class="px-3 py-3 font-mono text-xs">{{ $change['path'] }}</td>
                            <td class="max-w-sm px-3 py-3 font-mono text-xs">{{ json_encode($change['before'], JSON_UNESCAPED_SLASHES) }}</td>
                            <td class="max-w-sm px-3 py-3 font-mono text-xs">{{ json_encode($change['after'], JSON_UNESCAPED_SLASHES) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-text-muted">These snapshots are identical.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-6 space-y-3">
        <h2 class="font-heading text-2xl font-bold">Revisions</h2>
        @foreach ($revisions as $revision)
            <article class="admin-card min-w-0 flex flex-col justify-between gap-4 md:flex-row md:items-center" wire:key="history-revision-{{ $revision->id }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-heading text-lg font-bold">Revision {{ $revision->revision_number }}</h3>
                        <span class="admin-pill bg-surface text-text-muted">{{ $revision->source->value }}</span>
                        @if ($entry->published_revision_id === $revision->id)
                            <span class="admin-pill bg-primary/10 text-primary">Published</span>
                        @endif
                    </div>
                    <p class="mt-1 break-all text-sm text-text-muted">{{ $revision->summary ?: 'No revision note' }}</p>
                    <p class="mt-1 text-xs text-text-muted">
                        {{ $revision->user?->name ?? 'System' }} · {{ $revision->created_at?->format('M j, Y g:i A') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="admin-button admin-button-quiet"
                    wire:click="rollback({{ $revision->id }})"
                    wire:confirm="Publish a new rollback revision from revision {{ $revision->revision_number }}?"
                >Roll back to this</button>
            </article>
        @endforeach
    </section>

    <section class="admin-card">
        <h2 class="font-heading text-xl font-bold">Audit trail</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($events as $event)
                <li class="border-t border-border pt-3 first:border-0 first:pt-0">
                    <p class="font-semibold">{{ str($event->action)->headline() }}</p>
                    <p class="text-xs text-text-muted">
                        {{ $event->created_at?->format('M j, Y g:i A') }} · {{ $event->user?->name ?? 'System/API' }}
                        @if ($event->apiKey)
                            · {{ $event->apiKey->name }} ({{ $event->apiKey->displayPrefix() }})
                        @endif
                    </p>
                </li>
            @endforeach
        </ol>
    </section>
</div>
