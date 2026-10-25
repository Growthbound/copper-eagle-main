{{--
    Defaults to the service records in content/services, so the cards here, the
    dropdown on the enquiry form, and the service pages can never disagree
    about what the business offers.

    Pass "services" only when a page needs a deliberately different selection.

    When the service records carry a line icon, the grid is the services hub:
    four per row, the icon above a two-line title, centered, no card chrome,
    each tile linking to its service page. Without icons it keeps the photo
    cards.
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
            'icon' => method_exists($service, 'icon') ? $service->icon() : null,
        ])
        ->all();
    $hasIcons = collect($items)->contains(
        fn (mixed $item): bool => is_array($item) && is_string($item['icon'] ?? null) && trim($item['icon']) !== '',
    );

    /*
     * The hub breaks each title onto two lines the way the brand sets them:
     * before a "+" ("Drain Cleaning" / "+ Repairs"), or between the words of
     * a two-word name ("Gas" / "Lines"). Anything else wraps naturally.
     */
    $titleLines = static function (string $title): array {
        if (str_contains($title, ' + ')) {
            [$first, $rest] = explode(' + ', $title, 2);

            return [$first, '+ '.$rest];
        }

        $words = preg_split('/\s+/', trim($title)) ?: [];

        return count($words) === 2 ? $words : [$title];
    };
@endphp

<section
    id="{{ $id }}"
    @class(['services-panel', 'section-space', 'is-icon-grid' => $hasIcons])
    aria-labelledby="{{ $id }}-title"
>
    <div class="section-heading">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}-title">{{ $heading }}</h2>
    </div>

    @if ($hasIcons)
        <ul class="service-icon-grid">
            @foreach ($items as $service)
                <li class="service-icon-card">
                    @if (filled($service['icon'] ?? null))
                        <img
                            src="{{ $data->assetUrl($service['icon']) }}"
                            alt=""
                            width="320"
                            height="320"
                            loading="lazy"
                        >
                    @endif

                    <h3>
                        @if (filled($service['href'] ?? null))
                            <a href="{{ $service['href'] }}">
                                @foreach ($titleLines($service['title']) as $line)
                                    <span>{{ $line }}</span>
                                @endforeach
                            </a>
                        @else
                            @foreach ($titleLines($service['title']) as $line)
                                <span>{{ $line }}</span>
                            @endforeach
                        @endif
                    </h3>
                </li>
            @endforeach
        </ul>
    @else
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
    @endif
</section>
