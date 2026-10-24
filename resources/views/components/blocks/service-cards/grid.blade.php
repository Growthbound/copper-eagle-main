{{--
    Defaults to the service records in content/services, so the cards here, the
    dropdown on the enquiry form, and the service pages can never disagree
    about what the business offers.

    Pass "services" only when a page needs a deliberately different selection.
--}}
@props([
    'id' => 'services',
    'eyebrow' => null,
    'heading',
    'services' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $items = $services ?? app(App\Content\ServiceRepository::class)->all()
        ->map(fn (App\Content\Service $service): array => [
            'title' => $service->name(),
            'body' => $service->summary() ?? '',
            'href' => $service->url(),
            'image' => $service->image(),
        ])
        ->all();
@endphp

<section id="{{ $id }}" class="services-panel section-space" aria-labelledby="{{ $id }}-title">
    <div class="section-heading">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}-title">{{ $heading }}</h2>
    </div>

    <div class="service-grid">
        @foreach ($items as $service)
            <article class="service-card">
                @if (filled($service['image'] ?? null))
                    <img
                        src="{{ $data->assetUrl($service['image']['src']) }}"
                        alt="{{ $service['image']['alt'] ?? $service['title'] }}"
                        width="{{ $service['image']['width'] }}"
                        height="{{ $service['image']['height'] }}"
                        loading="lazy"
                    >
                @endif

                <div class="glass-card">
                    <h3>
                        @if (filled($service['href'] ?? null))
                            <a href="{{ $service['href'] }}">{{ $service['title'] }}</a>
                        @else
                            {{ $service['title'] }}
                        @endif
                    </h3>
                    <p>{{ $service['body'] }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>
