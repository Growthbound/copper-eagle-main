{{--
    A linked list of places or services. This is the main internal path into
    the service-by-area pages, so the links matter as much as the layout.
--}}
@props([
    'id' => 'service-areas',
    'eyebrow' => null,
    'heading',
    'areas' => [],
])

@if (filled($areas))
    <section id="{{ $id }}" class="areas-section section-space" aria-labelledby="{{ $id }}-title">
        <div class="section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </div>

        <ul class="area-grid">
            @foreach ($areas as $area)
                @php($name = $area['name'] ?? $area['label'] ?? null)
                @if (filled($name))
                    <li>
                        @if (filled($area['href'] ?? null))
                            <a href="{{ $area['href'] }}">{{ $name }}</a>
                        @else
                            <span>{{ $name }}</span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </section>
@endif
