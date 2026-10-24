<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">API keys</h1>
    <p class="mb-6 max-w-3xl text-sm text-text-muted">
        Create credentials for trusted tools that need to use the site content API. The username is your admin email:
        <strong class="text-text">{{ auth()->user()->email }}</strong>
    </p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    @if ($newKey)
        <section class="admin-card mb-8 border-2 border-accent" aria-labelledby="new-key-title">
            <div class="mb-4">
                <p class="mb-1 text-xs font-bold uppercase tracking-widest text-accent-on-page">One-time secret</p>
                <h2 id="new-key-title" class="font-heading text-xl font-bold">Copy this key now</h2>
                <p class="mt-2 text-sm text-text-muted">It cannot be recovered after you dismiss it or leave this page.</p>
            </div>

            <div class="flex min-w-0 flex-col gap-3 sm:flex-row" x-data="{ copied: false }">
                <input
                    x-ref="key"
                    type="text"
                    class="admin-input min-w-0 font-mono text-xs"
                    readonly
                    value="{{ $newKey }}"
                    onfocus="this.select()"
                    aria-label="New API key"
                >
                <button
                    type="button"
                    class="admin-button shrink-0"
                    x-on:click="navigator.clipboard.writeText($refs.key.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                >
                    <span x-show="! copied">Copy key</span>
                    <span x-cloak x-show="copied">Copied</span>
                </button>
                <button type="button" class="admin-button admin-button-quiet shrink-0" wire:click="dismissPlaintext">
                    I saved it
                </button>
            </div>
        </section>
    @endif

    <form wire:submit="generate" class="admin-card mb-8">
        <div class="grid items-end gap-4 md:grid-cols-[1fr_auto]">
            <div>
                <label class="admin-label" for="key-name">Key name</label>
                <input
                    id="key-name"
                    type="text"
                    class="admin-input"
                    placeholder="Local media migration"
                    maxlength="120"
                    wire:model="name"
                >
                <p class="mt-2 text-sm text-text-muted">Use a name that identifies the machine or workflow using it.</p>
                @error('name')
                    <p class="admin-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="admin-button">Generate key</button>
        </div>

        <fieldset class="mt-5">
            <legend class="admin-label">Scopes</legend>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($scopeOptions as $scope)
                    <label class="rounded-xl border border-border bg-surface p-3">
                        <span class="flex items-start gap-3">
                            <input type="checkbox" value="{{ $scope['value'] }}" wire:model="selectedScopes">
                            <span>
                                <span class="font-semibold">{{ $scope['label'] }}</span>
                                @if ($scope['high_impact'])
                                    <span class="admin-pill ml-1 bg-danger/10 text-danger">Public impact</span>
                                @endif
                                <span class="mt-1 block text-xs text-text-muted">{{ $scope['description'] }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('selectedScopes') <p class="admin-error">{{ $message }}</p> @enderror
            @error('selectedScopes.*') <p class="admin-error">{{ $message }}</p> @enderror
        </fieldset>
    </form>

    <div class="mb-4 flex items-end justify-between gap-4">
        <div>
            <h2 class="font-heading text-2xl font-bold">Key history</h2>
            <p class="mt-1 text-sm text-text-muted">Revoked keys stay here as a permanent audit trail.</p>
        </div>
    </div>

    @if ($keys->isEmpty())
        <div class="admin-card text-center text-sm text-text-muted">
            <p>No API keys yet.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($keys as $key)
                <article class="admin-card" wire:key="api-key-{{ $key->id }}">
                    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h3 class="font-heading text-lg font-bold">{{ $key->name }}</h3>
                                @if ($key->isRevoked())
                                    <span class="rounded-full bg-danger/10 px-2.5 py-1 text-xs font-bold text-danger">Revoked</span>
                                @else
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">Active</span>
                                @endif
                            </div>

                            <p class="break-all font-mono text-sm text-text">{{ $key->displayPrefix() }}</p>
                            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-text-muted">
                                <div>
                                    <dt class="inline font-semibold text-text">Created:</dt>
                                    <dd class="inline">{{ $key->created_at->format('M j, Y g:i A') }}</dd>
                                </div>
                                <div>
                                    <dt class="inline font-semibold text-text">Last used:</dt>
                                    <dd class="inline">{{ $key->last_used_at?->format('M j, Y g:i A') ?? 'Never' }}</dd>
                                </div>
                                @if ($key->revoked_at)
                                    <div>
                                        <dt class="inline font-semibold text-text">Revoked:</dt>
                                        <dd class="inline">{{ $key->revoked_at->format('M j, Y g:i A') }}</dd>
                                    </div>
                                @endif
                            </dl>

                            <fieldset class="mt-5">
                                <legend class="admin-label">Allowed operations</legend>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($scopeOptions as $scope)
                                        <label class="flex items-start gap-2 rounded-lg bg-surface px-3 py-2 text-sm">
                                            <input
                                                type="checkbox"
                                                value="{{ $scope['value'] }}"
                                                wire:model="keyScopes.{{ $key->id }}"
                                                @disabled($key->isRevoked())
                                            >
                                            <span>
                                                <span class="font-semibold">{{ $scope['label'] }}</span>
                                                @if ($scope['high_impact'])
                                                    <span class="block text-xs font-semibold text-danger">Changes public state</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('keyScopes.'.$key->id) <p class="admin-error">{{ $message }}</p> @enderror
                                @unless ($key->isRevoked())
                                    <button
                                        type="button"
                                        class="admin-button admin-button-quiet mt-3"
                                        wire:click="saveScopes({{ $key->id }})"
                                        wire:confirm="Replace the allowed operations for this active key?"
                                    >Save scopes</button>
                                @endunless
                            </fieldset>
                        </div>

                        @unless ($key->isRevoked())
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="admin-button admin-button-quiet"
                                    wire:click="rotate({{ $key->id }})"
                                    wire:confirm="Rotate this key? The current key will stop working immediately."
                                >
                                    Rotate
                                </button>
                                <button
                                    type="button"
                                    class="admin-button admin-button-quiet"
                                    wire:click="revoke({{ $key->id }})"
                                    wire:confirm="Revoke this key immediately? Any tool using it will lose access."
                                >
                                    Revoke
                                </button>
                            </div>
                        @endunless
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
