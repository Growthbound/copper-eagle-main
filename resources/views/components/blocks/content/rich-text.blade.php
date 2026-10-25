@props([
    'id' => 'rich-content',
    'eyebrow' => null,
    'heading',
    'nodes' => [],
    'media' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $normalizedNodes = app(\App\Content\RichContent::class)->normalizeNodes($nodes);
    // Long-form copy can carry a companion image that stays in view while the
    // text scrolls past it. Optional: without it the reading column is
    // unchanged, which matters because 47 migrated articles use this block.
    $rail = collect(is_array($media) ? $media : [])
        ->map(fn (mixed $image): ?array => $data->media($image))
        ->filter()
        ->take(2)
        ->values();
@endphp

<section id="{{ $id }}" @class(['rich-content-section', 'section-space', 'has-rail' => $rail->isNotEmpty()]) aria-labelledby="{{ $id }}-title">
    {{--
        Without a rail the heading spans the measure above the copy, as it does
        everywhere else. With one it moves into the copy column so the heading,
        the paragraph and the photograph all start on the same line.
    --}}
    @php
        $header = view('components.content.rich-text-header', [
            'id' => $id,
            'eyebrow' => $eyebrow,
            'heading' => $heading,
        ]);
    @endphp

    @if ($rail->isEmpty())
        {{ $header }}
    @endif

    <div class="rich-content-body">
        <div class="rich-content-reading">
            @if ($rail->isNotEmpty())
                {{ $header }}
            @endif

            @foreach ($normalizedNodes as $node)
                <x-content.rich-node :node="$node" />
            @endforeach
        </div>

        @if ($rail->isNotEmpty())
            <aside class="rich-content-rail">
                @foreach ($rail as $image)
                    <img
                        src="{{ $data->assetUrl($image['src']) }}"
                        alt="{{ $image['alt'] }}"
                        width="{{ $image['width'] }}"
                        height="{{ $image['height'] }}"
                        loading="lazy"
                    >
                    @if (filled($image['caption'] ?? null))
                        <p class="rich-content-rail-caption">{{ $image['caption'] }}</p>
                    @endif
                @endforeach
            </aside>
        @endif
    </div>
</section>
