<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">Tracking</h1>
    <p class="mb-6 text-sm text-text-muted">
        Analytics and widget IDs for this site. Each script loads only when its field is filled in, so anything you leave
        blank adds nothing to the page. These are stored with the site data, not in the code, so they survive updates.
    </p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="save">
        <div class="admin-card">
            @foreach ($fields as $field => $definition)
                <div class="mb-6">
                    <label class="admin-label" for="field-{{ $field }}">{{ $definition['label'] }}</label>
                    <p class="mb-2 text-sm text-text-muted">{{ $definition['help'] }}</p>
                    <input
                        id="field-{{ $field }}"
                        type="text"
                        class="admin-input font-mono"
                        spellcheck="false"
                        placeholder="{{ $definition['placeholder'] }}"
                        wire:model="integrations.{{ $field }}"
                    >
                    @error('integrations.'.$field)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="admin-card mt-6">
            <h2 class="font-heading text-xl font-bold">Custom code</h2>
            <p class="mt-1 mb-5 text-sm text-text-muted">
                For anything without a field above, such as chat widgets, call tracking, or verification tags. Code here
                is added to every public page exactly as written, so paste only snippets you trust from the vendor.
            </p>

            @foreach ($snippets as $field => $definition)
                <div @class(['mb-6' => ! $loop->last])>
                    <label class="admin-label" for="field-{{ $field }}">{{ $definition['label'] }}</label>
                    <p class="mb-2 text-sm text-text-muted">{{ $definition['help'] }}</p>
                    <textarea
                        id="field-{{ $field }}"
                        rows="5"
                        class="admin-input font-mono text-xs"
                        spellcheck="false"
                        wire:model="integrations.{{ $field }}"
                    ></textarea>
                    @error('integrations.'.$field)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <button type="submit" class="admin-button mt-6">Save tracking</button>
    </form>
</div>
