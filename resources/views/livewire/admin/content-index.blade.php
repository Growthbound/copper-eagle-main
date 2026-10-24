<div>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-heading text-3xl font-bold tracking-tight">Content</h1>
            <p class="mt-2 max-w-3xl text-sm text-text-muted">
                Governed pages, records, drafts, schedules, and publication history.
            </p>
        </div>
        <a class="admin-button admin-button-quiet" href="{{ route('admin.redirects') }}" wire:navigate>Manage redirects</a>
    </div>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="create" class="admin-card mb-8">
        <h2 class="mb-4 font-heading text-xl font-bold">Start a governed draft</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="admin-label" for="content-title">Title</label>
                <input id="content-title" class="admin-input" wire:model="newTitle" placeholder="Helpful homeowner guide">
                @error('newTitle') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div class="lg:col-span-2">
                <label class="admin-label" for="content-slug">Slug</label>
                <input id="content-slug" class="admin-input font-mono" wire:model="newSlug" placeholder="guides/helpful-homeowner-guide">
                @error('newSlug') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="content-kind">Kind</label>
                <select id="content-kind" class="admin-input" wire:model="newKind">
                    @foreach ($creatableKinds as $kind)
                        <option value="{{ $kind->value }}">{{ str($kind->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="content-template">Template</label>
                <select id="content-template" class="admin-input" wire:model="newTemplate">
                    @foreach ($templates as $template)
                        <option value="{{ $template->value }}">{{ str($template->value)->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end lg:col-span-2">
                <button class="admin-button w-full" type="submit">Create draft</button>
            </div>
        </div>
    </form>

    <div class="admin-card mb-6 grid gap-4 md:grid-cols-3">
        <div>
            <label class="admin-label" for="content-search">Search</label>
            <input id="content-search" class="admin-input" wire:model.live.debounce.250ms="search" placeholder="Title or slug">
        </div>
        <div>
            <label class="admin-label" for="status-filter">Status</label>
            <select id="status-filter" class="admin-input" wire:model.live="statusFilter">
                <option value="all">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ str($status->value)->headline() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="admin-label" for="kind-filter">Kind</label>
            <select id="kind-filter" class="admin-input" wire:model.live="kindFilter">
                <option value="all">All kinds</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->value }}">{{ str($kind->value)->headline() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($entries as $entry)
            <article class="admin-card flex flex-col justify-between gap-5 md:flex-row md:items-center" wire:key="content-{{ $entry->id }}">
                <div class="min-w-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h2 class="font-heading text-lg font-bold">{{ $entry->title }}</h2>
                        <span class="admin-pill bg-primary/10 text-primary">{{ $entry->status->value }}</span>
                        <span class="admin-pill bg-surface text-text-muted">{{ $entry->kind->value }}</span>
                    </div>
                    <p class="break-all font-mono text-sm text-text-muted">/{{ ltrim($entry->slug, '/') }}</p>
                    <p class="mt-2 text-xs text-text-muted">
                        {{ $entry->revisions_count }} revision(s) · updated {{ $entry->updated_at?->diffForHumans() }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a class="admin-button" href="{{ route('admin.content.edit', $entry) }}" wire:navigate>Edit</a>
                    <a class="admin-button admin-button-quiet" href="{{ route('admin.content.history', $entry) }}" wire:navigate>History</a>
                    @if ($entry->published_revision_id)
                        <a class="admin-button admin-button-quiet" href="{{ url($entry->slug) }}">View</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="admin-card text-center text-sm text-text-muted">No managed content matches these filters.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $entries->links() }}</div>
</div>
