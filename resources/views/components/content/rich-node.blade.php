@props(['node'])

@switch($node['type'])
    @case('heading')
        @switch($node['level'])
            @case(3)
                <h3 @if (isset($node['id'])) id="{{ $node['id'] }}" @endif>{{ $node['text'] }}</h3>
                @break
            @case(4)
                <h4 @if (isset($node['id'])) id="{{ $node['id'] }}" @endif>{{ $node['text'] }}</h4>
                @break
            @case(5)
                <h5 @if (isset($node['id'])) id="{{ $node['id'] }}" @endif>{{ $node['text'] }}</h5>
                @break
            @case(6)
                <h6 @if (isset($node['id'])) id="{{ $node['id'] }}" @endif>{{ $node['text'] }}</h6>
                @break
        @endswitch
        @break

    @case('paragraph')
        <p><x-content.inline-segments :segments="$node['segments']" /></p>
        @break

    @case('unordered-list')
        <ul>
            @foreach ($node['items'] as $item)
                <li><x-content.inline-segments :segments="$item" /></li>
            @endforeach
        </ul>
        @break

    @case('ordered-list')
        <ol @if (isset($node['start'])) start="{{ $node['start'] }}" @endif>
            @foreach ($node['items'] as $item)
                <li><x-content.inline-segments :segments="$item" /></li>
            @endforeach
        </ol>
        @break

    @case('image')
        @php
            $image = app(\App\Content\BlockData::class)->media($node);
        @endphp
        @if ($image)
            <figure class="rich-content-media rich-content-media--{{ $node['layout'] ?? 'inline' }}">
                <img
                    src="{{ str_starts_with($image['src'], 'http') || str_starts_with($image['src'], '/') ? $image['src'] : asset($image['src']) }}"
                    alt="{{ $image['alt'] }}"
                    width="{{ $image['width'] }}"
                    height="{{ $image['height'] }}"
                    loading="lazy"
                >
                @if (filled($image['caption'] ?? null))
                    <figcaption>{{ $image['caption'] }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('callout')
        <aside class="rich-content-callout rich-content-callout--{{ $node['tone'] }}" aria-label="{{ ucfirst($node['tone']) }}">
            <span class="rich-content-callout-label">{{ ucfirst($node['tone']) }}</span>
            <p class="rich-content-callout-title">{{ $node['title'] }}</p>
            <p><x-content.inline-segments :segments="$node['segments']" /></p>
        </aside>
        @break

    @case('quote')
        <blockquote class="rich-content-quote">
            <p>{{ $node['quote'] }}</p>
            @if (filled($node['attribution'] ?? null) || filled($node['role'] ?? null))
                <footer>
                    @if (filled($node['attribution'] ?? null))
                        <cite>
                            @if (filled($node['source_url'] ?? null))
                                <a href="{{ $node['source_url'] }}" @if (preg_match('#^https?://#i', $node['source_url']) === 1) rel="noopener noreferrer" @endif>{{ $node['attribution'] }}</a>
                            @else
                                {{ $node['attribution'] }}
                            @endif
                        </cite>
                    @endif
                    @if (filled($node['role'] ?? null))
                        <span>{{ $node['role'] }}</span>
                    @endif
                </footer>
            @endif
        </blockquote>
        @break

    @case('divider')
        <hr>
        @break
@endswitch
