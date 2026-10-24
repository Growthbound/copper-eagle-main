<div>
    <div class="mb-8">
        <h1 class="font-heading text-3xl font-bold tracking-tight">Navigation and footer</h1>
        <p class="mt-2 max-w-3xl text-sm text-text-muted">Edit shared site links once. Publishing updates every page that uses the shared chrome.</p>
    </div>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif
    @error('links') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror
    @error('conflict') <p class="mb-6 rounded-lg bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">{{ $message }}</p> @enderror

    <form wire:submit="save" class="space-y-6">
        @foreach ([
            ['navigationLinks', 'Primary navigation', 'The desktop and mobile header use the same ordered list.'],
            ['companyLinks', 'Footer company links', 'Company and service destinations.'],
            ['resourceLinks', 'Footer resource links', 'Guides, summaries, and discovery files.'],
        ] as [$collection, $heading, $description])
            <section class="admin-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-xl font-bold">{{ $heading }}</h2>
                        <p class="mt-1 text-sm text-text-muted">{{ $description }}</p>
                    </div>
                    <button type="button" class="admin-button admin-button-quiet" wire:click="addLink('{{ $collection }}')">Add link</button>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach ($this->{$collection} as $index => $link)
                        <div class="grid gap-3 rounded-xl bg-surface p-3 md:grid-cols-[1fr_2fr_auto]" wire:key="{{ $collection }}-{{ $index }}">
                            <div>
                                <label class="admin-label" for="{{ $collection }}-label-{{ $index }}">Label</label>
                                <input id="{{ $collection }}-label-{{ $index }}" class="admin-input" wire:model="{{ $collection }}.{{ $index }}.label">
                            </div>
                            <div>
                                <label class="admin-label" for="{{ $collection }}-href-{{ $index }}">URL</label>
                                <input id="{{ $collection }}-href-{{ $index }}" class="admin-input font-mono" wire:model="{{ $collection }}.{{ $index }}.href">
                            </div>
                            <button type="button" class="self-end pb-3 text-sm font-semibold text-danger" wire:click="removeLink('{{ $collection }}', {{ $index }})">Remove</button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <button type="submit" class="admin-button" wire:confirm="Publish these shared navigation and footer links?">Publish site structure</button>
    </form>
</div>
