<div>
    <div class="mb-8">
        <h1 class="font-heading text-3xl font-bold tracking-tight">Redirects</h1>
        <p class="mt-2 max-w-3xl text-sm text-text-muted">Exact, one-hop URL migrations. Chains, loops, unsafe schemes, and protected sources are rejected.</p>
    </div>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif
    @error('redirect') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror

    <form wire:submit="save" class="admin-card mb-8">
        <h2 class="mb-4 font-heading text-xl font-bold">{{ $editingId ? 'Edit redirect' : 'Add redirect' }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="admin-label" for="redirect-source">Exact source path</label>
                <input id="redirect-source" class="admin-input font-mono" wire:model="sourcePath" placeholder="/legacy-page">
                @error('sourcePath') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="redirect-target">Final target</label>
                <input id="redirect-target" class="admin-input font-mono" wire:model="target" placeholder="/replacement-page">
                @error('target') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="admin-label" for="redirect-status">Status</label>
                <select id="redirect-status" class="admin-input" wire:model="statusCode">
                    <option value="301">301 permanent</option>
                    <option value="302">302 temporary</option>
                </select>
                @error('statusCode') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-3 self-end pb-3 text-sm font-semibold">
                <input type="checkbox" wire:model="enabled"> Enabled
            </label>
            <div class="md:col-span-2">
                <label class="admin-label" for="redirect-notes">Decision notes</label>
                <textarea id="redirect-notes" class="admin-input min-h-24" wire:model="notes" placeholder="Why this is an equivalent destination and who approved it"></textarea>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button class="admin-button" type="submit">Save redirect</button>
            @if ($editingId)
                <button class="admin-button admin-button-quiet" type="button" wire:click="cancelEdit">Cancel</button>
            @endif
        </div>
    </form>

    <div class="space-y-3">
        @forelse ($rules as $rule)
            <article class="admin-card flex flex-col justify-between gap-4 md:flex-row md:items-center" wire:key="redirect-{{ $rule->id }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="admin-pill {{ $rule->enabled ? 'bg-primary/10 text-primary' : 'bg-surface text-text-muted' }}">
                            {{ $rule->enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                        <strong>{{ $rule->status_code }}</strong>
                    </div>
                    <p class="mt-2 break-all font-mono text-sm">{{ $rule->source_path }} → {{ $rule->target }}</p>
                    @if ($rule->notes)
                        <p class="mt-2 text-sm text-text-muted">{{ $rule->notes }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <button class="admin-button admin-button-quiet" type="button" wire:click="edit({{ $rule->id }})">Edit</button>
                    <button class="admin-button admin-button-quiet" type="button" wire:click="toggle({{ $rule->id }})">{{ $rule->enabled ? 'Disable' : 'Enable' }}</button>
                    <button class="admin-button admin-button-quiet text-danger" type="button" wire:click="delete({{ $rule->id }})" wire:confirm="Delete this redirect rule?">Delete</button>
                </div>
            </article>
        @empty
            <div class="admin-card text-center text-sm text-text-muted">No managed redirects.</div>
        @endforelse
    </div>
</div>
