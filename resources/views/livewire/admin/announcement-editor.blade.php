<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">Announcement</h1>
    <p class="mb-6 text-sm text-text-muted">
        A bar across the top of every public page. Use it for seasonal offers, holiday hours, or service notices.
    </p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <div class="admin-card mb-6">
        <p class="admin-label mb-3">Showing on the site right now</p>

        @if ($preview)
            <x-dynamic-component
                :component="'blocks.announcement.'.$preview['variant']"
                :message="$preview['message']"
                :url="$preview['url']"
                :link-label="$preview['linkLabel']"
            />
        @else
            <p class="rounded-lg bg-surface px-4 py-3 text-sm text-text-muted">
                Nothing. The announcement is switched off, has no message, or is outside its scheduled window.
            </p>
        @endif
    </div>

    <form wire:submit="save">
        <div class="admin-card">
            <label class="mb-6 flex items-center gap-3 text-sm font-semibold">
                <input type="checkbox" class="size-4 rounded border-border" wire:model.live="enabled">
                Show the announcement
            </label>

            <div class="mb-6">
                <label class="admin-label" for="announcement-message">Message</label>
                <p class="mb-2 text-sm text-text-muted">One short sentence. This sits above the header on every page.</p>
                <input id="announcement-message" type="text" class="admin-input" wire:model="message">
                @error('message') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="admin-label" for="announcement-url">Link address</label>
                    <p class="mb-2 text-sm text-text-muted">Optional. Where the message should lead.</p>
                    <input id="announcement-url" type="url" class="admin-input" placeholder="https://" wire:model="url">
                    @error('url') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="admin-label" for="announcement-link-label">Link text</label>
                    <p class="mb-2 text-sm text-text-muted">Defaults to "Learn more".</p>
                    <input id="announcement-link-label" type="text" class="admin-input" wire:model="linkLabel">
                    @error('linkLabel') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mb-6">
                <label class="admin-label" for="announcement-variant">Style</label>
                <p class="mb-2 text-sm text-text-muted">Every announcement template built for this site appears here.</p>
                <select id="announcement-variant" class="admin-input" wire:model.live="variant">
                    @foreach ($variants as $option)
                        <option value="{{ $option }}">{{ ucfirst(str_replace('-', ' ', $option)) }}</option>
                    @endforeach
                </select>
                @error('variant') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="admin-label" for="announcement-starts">Start showing</label>
                    <p class="mb-2 text-sm text-text-muted">Leave blank to start immediately.</p>
                    <input id="announcement-starts" type="datetime-local" class="admin-input" wire:model="startsAt">
                    @error('startsAt') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="admin-label" for="announcement-ends">Stop showing</label>
                    <p class="mb-2 text-sm text-text-muted">Leave blank to run until switched off.</p>
                    <input id="announcement-ends" type="datetime-local" class="admin-input" wire:model="endsAt">
                    @error('endsAt') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="mt-3 text-xs text-text-muted">Times are in {{ $timezone }}.</p>
        </div>

        <button type="submit" class="admin-button mt-6">Save announcement</button>
    </form>
</div>
