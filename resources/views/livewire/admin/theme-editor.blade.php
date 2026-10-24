<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">Theme</h1>
    <p class="mb-6 text-sm text-text-muted">These colors drive the public site. Changes apply everywhere as soon as you save.</p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="save">
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($definitions as $token => $definition)
                <div class="admin-card" wire:key="color-{{ $token }}">
                    <label class="admin-label" for="color-{{ $token }}">{{ $definition['label'] }}</label>
                    <p class="mb-3 text-sm text-text-muted">{{ $definition['help'] }}</p>

                    <div class="flex items-center gap-3">
                        <input
                            id="color-{{ $token }}"
                            type="color"
                            class="size-11 shrink-0 cursor-pointer rounded-lg border border-border bg-page p-1"
                            wire:model.live="colors.{{ $token }}"
                        >
                        <input
                            type="text"
                            class="admin-input font-mono"
                            spellcheck="false"
                            aria-label="{{ $definition['label'] }} hex value"
                            wire:model.live.debounce.400ms="colors.{{ $token }}"
                        >
                    </div>

                    @error('colors.'.$token)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror

                    @if ($score = $contrasts[$token] ?? null)
                        <p @class([
                            'mt-3 text-xs font-semibold',
                            'text-text-muted' => $score['passes'],
                            'text-danger' => ! $score['passes'],
                        ])>
                            Contrast against {{ strtolower($score['against']) }}: {{ number_format($score['ratio'], 2) }}:1
                            {{ $score['passes'] ? '— meets WCAG AA' : '— below the 4.5:1 minimum for text' }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="admin-button">Save theme</button>
            <button
                type="button"
                class="admin-button admin-button-quiet"
                wire:click="resetToDefaults"
                wire:confirm="Reset every color back to the original palette?"
            >
                Reset to defaults
            </button>
        </div>
    </form>
</div>
