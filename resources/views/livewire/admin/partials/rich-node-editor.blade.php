@props(['path', 'nodes' => []])

<div class="mt-3 rounded-xl border border-border bg-page p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="font-semibold">Rich content nodes</p>
            <p class="text-xs text-text-muted">H3–H6, paragraphs, lists, callouts, quotes, images, and dividers.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['paragraph', 'heading', 'unordered-list', 'ordered-list', 'callout', 'quote', 'divider'] as $type)
                <button type="button" class="admin-button admin-button-quiet" wire:click="addRichNode('{{ $path }}', '{{ $type }}')">
                    + {{ str($type)->headline() }}
                </button>
            @endforeach
        </div>
    </div>

    <ol class="mt-4 space-y-2">
        @forelse ($nodes as $index => $node)
            <li class="flex items-center justify-between gap-3 rounded-lg bg-surface px-3 py-2">
                <span class="text-sm font-semibold">{{ $index + 1 }}. {{ str($node['type'] ?? 'unknown')->headline() }}</span>
                <button type="button" class="text-sm font-semibold text-danger" wire:click="removeRichNode('{{ $path }}', {{ $index }})">Remove</button>
            </li>
        @empty
            <li class="text-sm text-text-muted">No rich nodes yet.</li>
        @endforelse
    </ol>
</div>
