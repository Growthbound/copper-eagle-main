<div>
    <h1 class="mb-6 font-heading text-3xl font-bold tracking-tight">Media</h1>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    {{--
        The upload endpoint rejects an oversized file before the component runs,
        so its validation message never fires and the progress text would
        otherwise sit at "Uploading…" indefinitely. Listening for the failure
        keeps that from looking like a hang.
    --}}
    <div
        class="admin-card mb-8"
        x-data="{ uploadError: null }"
        x-on:livewire-upload-start="uploadError = null"
        x-on:livewire-upload-error="uploadError = 'That upload was refused. Each media file must be 25 MB or smaller and be a JPG, PNG, WebP, AVIF, GIF, or MP4.'"
    >
        <label class="admin-label" for="media-uploads">Upload media</label>
        <input
            id="media-uploads"
            type="file"
            class="admin-input"
            multiple
            accept="image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4"
            wire:model="uploads"
        >
        <p class="mt-2 text-sm text-text-muted">JPG, PNG, WebP, AVIF, GIF, or MP4. Up to 25 MB each.</p>

        <p wire:loading wire:target="uploads" class="mt-2 text-sm font-semibold" x-show="! uploadError">Uploading…</p>

        <p class="admin-error" x-show="uploadError" x-text="uploadError" x-cloak></p>

        @error('uploads.*')
            <p class="admin-error">{{ $message }}</p>
        @enderror
    </div>

    @unless ($libraryIsEmpty)
        <div class="admin-card mb-6 flex flex-wrap items-end gap-4">
            <div class="min-w-56 flex-1">
                <label class="admin-label" for="media-search">Search</label>
                <input
                    id="media-search"
                    type="search"
                    class="admin-input"
                    placeholder="File name, title, description, or handle"
                    wire:model.live.debounce.300ms="search"
                >
            </div>

            <div>
                <label class="admin-label" for="media-kind-filter">Media kind</label>
                <select id="media-kind-filter" class="admin-input" wire:model.live="kindFilter">
                    <option value="all">All media</option>
                    <option value="image">Images</option>
                    <option value="video">Videos</option>
                </select>
            </div>

            @if (filled($search))
                <button type="button" class="admin-button admin-button-quiet" wire:click="$set('search', '')">
                    Clear
                </button>
            @endif
        </div>
    @endunless

    @error('delete')
        <p class="admin-error mb-4">{{ $message }}</p>
    @enderror

    @if ($media->isEmpty())
        <div class="admin-card text-center text-sm text-text-muted">
            @if ($libraryIsEmpty)
                <p>No media yet. Upload a file above and its address will appear here.</p>
            @elseif (filled($search))
                <p>No media matches &ldquo;{{ $search }}&rdquo;. Search looks at file names, titles, descriptions, and handles.</p>
            @else
                <p>No media matches this filter.</p>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($media as $item)
                <div class="admin-card" wire:key="media-{{ $item->id }}">
                    @if ($item->kind === \App\Enums\MediaKind::Video)
                        <video controls preload="metadata" class="admin-media-preview">
                            <source src="{{ $item->url() }}" type="{{ $item->mime_type }}">
                            Your browser does not support MP4 video.
                        </video>
                    @else
                        <img
                            src="{{ $item->url() }}"
                            alt="{{ $item->alt ?: $item->original_name }}"
                            class="admin-media-preview"
                            loading="lazy"
                        >
                    @endif

                    <div class="admin-media-meta">
                        <span class="admin-pill bg-surface text-text-muted">{{ ucfirst($item->kind->value) }}</span>
                        <p class="truncate text-sm font-semibold" title="{{ $item->original_name }}">{{ $item->original_name }}</p>
                        <p class="text-xs text-text-muted">
                            {{ $item->readableSize() }}@if ($item->width && $item->height) · {{ $item->width }}×{{ $item->height }}@endif
                        </p>

                        @if ($item->title)
                            <p class="text-sm">{{ $item->title }}</p>
                        @endif

                        @if ($item->handle)
                            <p class="text-xs text-text-muted"><span class="font-semibold text-text">Handle:</span> {{ $item->handle }}</p>
                        @endif

                        @if ($sourceUrls[$item->id])
                            <p class="break-all text-xs text-text-muted">
                                <span class="font-semibold text-text">Imported from:</span> {{ $sourceUrls[$item->id] }}
                            </p>
                        @endif
                    </div>

                    <label class="admin-label" for="title-{{ $item->id }}">Title</label>
                    <input
                        id="title-{{ $item->id }}"
                        type="text"
                        class="admin-input"
                        placeholder="Internal title"
                        maxlength="255"
                        wire:model="title.{{ $item->id }}"
                        wire:blur="saveDetails({{ $item->id }})"
                    >
                    @error('title.'.$item->id)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror

                    <label class="admin-label mt-3" for="alt-{{ $item->id }}">Description</label>
                    <input
                        id="alt-{{ $item->id }}"
                        type="text"
                        class="admin-input"
                        placeholder="Describe the media"
                        maxlength="255"
                        wire:model="alt.{{ $item->id }}"
                        wire:blur="saveDetails({{ $item->id }})"
                    >
                    @error('alt.'.$item->id)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror

                    <label class="admin-label mt-3" for="handle-{{ $item->id }}">Handle</label>
                    <input
                        id="handle-{{ $item->id }}"
                        type="text"
                        class="admin-input"
                        placeholder="home-hero-media"
                        maxlength="120"
                        wire:model="handle.{{ $item->id }}"
                        wire:blur="saveDetails({{ $item->id }})"
                    >
                    @error('handle.'.$item->id)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror

                    <div class="mt-3 flex items-center gap-2">
                        <input
                            type="text"
                            class="admin-input text-xs"
                            readonly
                            value="{{ $item->url() }}"
                            onfocus="this.select()"
                            aria-label="Media address for {{ $item->original_name }}"
                        >
                        <button
                            type="button"
                            class="admin-button admin-button-quiet shrink-0"
                            wire:click="delete({{ $item->id }})"
                            wire:confirm="Delete this media? Any page using it will stop displaying it."
                        >
                            Delete
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $media->links() }}
        </div>
    @endif
</div>
