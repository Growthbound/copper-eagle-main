{{--
    The row of service line icons under the header on the home page ("Service
    icon strip" in docs/brand/design-system.md). hero/photo renders it; it can
    also stand alone.

    Reads the service records in content/services: every service with an
    `icon`, in record order, captioned with its name and linking to its page.
    Pass `services` (record slugs) only for a deliberately different
    selection; a slug without a record or a usable icon is left out. With
    nothing to show it renders nothing rather than an empty band.
--}}
@props([
    'id' => 'service-strip',
    'services' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $repository = app(\App\Content\ServiceRepository::class);

    $records = is_array($services)
        ? collect($services)
            ->filter(fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug): string => trim($slug))
            ->unique()
            ->map(fn (string $slug): ?\App\Content\Record => $repository->find($slug))
            ->filter()
        : $repository->all();

    $items = $records
        ->map(function (\App\Content\Record $service): ?array {
            $icon = $service instanceof \App\Content\Service ? $service->iconImage() : null;

            if ($icon === null) {
                return null;
            }

            /*
             * Captions sit on two short lines, as on the live site: before
             * and after a joining "+" or "&" when there is one ("Drain
             * cleaning / + repairs"), otherwise split between the words.
             */
            $name = trim((string) preg_replace('/\s+/u', ' ', $service->name()));
            $lines = preg_split('/\s+(?=[+&]\s)/u', $name, 2) ?: [$name];

            if (count($lines) === 1 && str_contains($name, ' ')) {
                $words = explode(' ', $name);
                $half = intdiv(count($words) + 1, 2);
                $lines = [
                    implode(' ', array_slice($words, 0, $half)),
                    implode(' ', array_slice($words, $half)),
                ];
            }

            return [
                'href' => $service->url(),
                'lines' => $lines,
                'icon' => $icon,
            ];
        })
        ->filter()
        ->values();
@endphp

@if ($items->isNotEmpty())
    <nav id="{{ $id }}" class="service-strip edge-panel" aria-label="Services">
        <ul class="service-strip-list" role="list">
            @foreach ($items as $item)
                <li>
                    <a class="service-strip-link" href="{{ $item['href'] }}">
                        <img
                            src="{{ $data->assetUrl($item['icon']['src']) }}"
                            alt=""
                            width="{{ $item['icon']['width'] }}"
                            height="{{ $item['icon']['height'] }}"
                            decoding="async"
                        >
                        <span class="service-strip-caption">
                            @foreach ($item['lines'] as $line)
                                <span>{{ $line }}</span>
                            @endforeach
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
