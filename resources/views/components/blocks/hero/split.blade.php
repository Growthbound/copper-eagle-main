@props([
    'eyebrow' => null,
    'heading',
    'body' => null,
    'cta' => null,
    'secondary' => null,
    'image' => null,
    'video' => null,
    'badge' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

<section class="hero-panel edge-panel dotted-panel" aria-labelledby="hero-title">
    <x-site-header :cta="$cta" />

    <div class="hero-grid">
        <div class="hero-copy">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            <h1 id="hero-title">{{ $heading }}</h1>

            @if (filled($body))
                <p>{{ $body }}</p>
            @endif

            @if (filled($cta) || filled($secondary))
                <div class="action-row">
                    @if (filled($cta))
                        <a class="button button-orange" href="{{ $cta['href'] }}">
                            {{ $cta['label'] }} <span aria-hidden="true">→</span>
                        </a>
                    @endif

                    @if (filled($secondary))
                        <a class="text-link" href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                    @endif
                </div>
            @endif
        </div>

        @if (filled($image))
            <div class="hero-media">
                <span class="hero-orbit" aria-hidden="true"></span>
                <img
                    src="{{ $data->assetUrl($image['src']) }}"
                    alt="{{ $image['alt'] }}"
                    width="{{ $image['width'] }}"
                    height="{{ $image['height'] }}"
                    fetchpriority="high"
                >
                @if (filled($video))
                    <video
                        class="hero-video"
                        data-lazy-hero-video
                        data-src="{{ $data->assetUrl($video['src']) }}"
                        data-type="{{ $video['type'] }}"
                        preload="none"
                        autoplay
                        muted
                        loop
                        playsinline
                        aria-hidden="true"
                    ></video>
                @endif
                @if (filled($badge))
                    <span class="airflow-badge" aria-hidden="true">{{ $badge }}</span>
                @endif
                @if (filled($video))
                    <button
                        class="hero-video-control"
                        type="button"
                        data-hero-video-control
                        aria-label="Pause background video"
                        disabled
                    >
                        <span class="hero-video-control__pause" data-video-pause-icon aria-hidden="true">
                            <span></span><span></span>
                        </span>
                        <span class="hero-video-control__play" data-video-play-icon aria-hidden="true" hidden></span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</section>
