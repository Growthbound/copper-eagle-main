{{--
    One image tile per social profile, each opening the profile in a new tab,
    beside an optional contact card (an image such as a vCard QR code, a short
    label, and a link). Tiles without an absolute http(s) link or complete
    image metadata are omitted; the tile's alt text is the link's name.
--}}
@props([
    'id' => 'social-profiles',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'profiles' => [],
    'card' => null,
])

@php
    $data = app(\App\Content\BlockData::class);

    $tiles = collect(is_array($profiles) ? $profiles : [])
        ->map(fn (mixed $profile): ?array => is_array($profile)
            && ($href = $data->externalUrl($profile['href'] ?? null)) !== null
            && ($image = $data->media($profile['image'] ?? null)) !== null
                ? ['href' => $href, 'image' => $image]
                : null)
        ->filter()
        ->values();

    $contactLink = is_array($card) ? $data->link($card) : null;
    $contactImage = $contactLink ? $data->media($card['image'] ?? null) : null;
@endphp

@if ($tiles->isNotEmpty())
    <section id="{{ $id }}" @class(['social-profiles', 'section-space', 'has-card' => $contactImage !== null]) aria-labelledby="{{ $id }}-title">
        <header class="social-profiles-heading">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            <h2 id="{{ $id }}-title">{{ $heading }}</h2>

            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
        </header>

        <div class="social-profiles-layout">
            <ul class="social-profiles-tiles" role="list">
                @foreach ($tiles as $tile)
                    <li class="social-profiles-tile">
                        <a href="{{ $tile['href'] }}" target="_blank" rel="noopener">
                            <img
                                src="{{ $data->assetUrl($tile['image']['src']) }}"
                                alt="{{ $tile['image']['alt'] }}"
                                width="{{ $tile['image']['width'] }}"
                                height="{{ $tile['image']['height'] }}"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="sr-only">(opens in a new tab)</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($contactImage)
                <a class="social-profiles-card" href="{{ $contactLink['href'] }}" target="_blank" rel="noopener">
                    <img
                        src="{{ $data->assetUrl($contactImage['src']) }}"
                        alt="{{ $contactImage['alt'] }}"
                        width="{{ $contactImage['width'] }}"
                        height="{{ $contactImage['height'] }}"
                        loading="lazy"
                    >
                    <span class="social-profiles-card-label">{{ $contactLink['label'] }}</span>
                </a>
            @endif
        </div>
    </section>
@endif
