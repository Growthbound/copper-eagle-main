{{--
    "Follow us": the heading and profile handles on the start side, a static
    grid of square post images on the end side. Each post opens on the social
    network in a new tab. A static grid, not an embed: no third-party script,
    no tracking, and the images are served from this site.

    Profiles without a valid optional link render as text; posts without an
    absolute http(s) link or complete image metadata are omitted.
--}}
@props([
    'id' => 'social',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'profiles' => [],
    'posts' => [],
])

@php
    $data = app(\App\Content\BlockData::class);

    $handles = collect(is_array($profiles) ? $profiles : [])
        ->filter(fn (mixed $profile): bool => is_array($profile)
            && filled($profile['label'] ?? null)
            && filled($profile['handle'] ?? null))
        ->map(fn (array $profile): array => [
            'label' => trim((string) $profile['label']),
            'handle' => trim((string) $profile['handle']),
            'href' => $data->externalUrl($profile['href'] ?? null),
        ])
        ->values();

    $feed = collect(is_array($posts) ? $posts : [])
        ->map(fn (mixed $post): ?array => is_array($post)
            && ($href = $data->externalUrl($post['href'] ?? null)) !== null
            && ($image = $data->media($post['image'] ?? null)) !== null
                ? ['href' => $href, 'image' => $image]
                : null)
        ->filter()
        ->take(12)
        ->values();
@endphp

<section id="{{ $id }}" @class(['social-feed', 'section-space', 'has-posts' => $feed->isNotEmpty()]) aria-labelledby="{{ $id }}-title">
    <div class="social-feed-intro">
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        @if ($handles->isNotEmpty())
            <ul class="social-feed-profiles" role="list">
                @foreach ($handles as $profile)
                    <li>
                        <span class="social-feed-network">{{ $profile['label'] }}</span>
                        @if ($profile['href'])
                            <a href="{{ $profile['href'] }}" target="_blank" rel="noopener">{{ $profile['handle'] }}<span class="sr-only"> (opens in a new tab)</span></a>
                        @else
                            <span>{{ $profile['handle'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($feed->isNotEmpty())
        <ul class="social-feed-posts" role="list">
            @foreach ($feed as $post)
                <li class="social-feed-post">
                    <a href="{{ $post['href'] }}" target="_blank" rel="noopener">
                        <img
                            src="{{ $data->assetUrl($post['image']['src']) }}"
                            alt="{{ $post['image']['alt'] }}"
                            width="{{ $post['image']['width'] }}"
                            height="{{ $post['image']['height'] }}"
                            loading="lazy"
                            decoding="async"
                        >
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
