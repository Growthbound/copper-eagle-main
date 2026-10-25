@props([
    'message',
    'url' => null,
    'linkLabel' => null,
])

<div class="announcement-bar" role="region" aria-label="Site announcement">
    <p>
        <span>{{ $message }}</span>

        @if (filled($url))
            <a href="{{ $url }}">{{ $linkLabel ?: 'Learn more' }}</a>
        @endif
    </p>
</div>
