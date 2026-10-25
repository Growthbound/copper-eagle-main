{{--
    A linked list of places or services. This is the main internal path into
    the service-by-area pages, so the links matter as much as the layout.

    Renders as the copper hexagon band: uppercase label, the area names on
    centered lines separated by small dots, and an optional small note. When
    an eyebrow is given it is the visible label and the heading is kept for
    assistive technology; without one, the heading is the visible label.
--}}
@props([
    'id' => 'service-areas',
    'eyebrow' => null,
    'heading',
    'areas' => [],
    'note' => null,
])

@if (filled($areas))
    <section id="{{ $id }}" class="areas-section section-space edge-panel" aria-labelledby="{{ $id }}-title">
        <div class="areas-band">
            <div class="areas-band-inner">
                @if (filled($eyebrow))
                    <p class="eyebrow">{{ $eyebrow }}</p>
                    <h2 id="{{ $id }}-title" class="sr-only">{{ $heading }}</h2>
                @else
                    <h2 id="{{ $id }}-title" class="areas-label">{{ $heading }}</h2>
                @endif

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

                @if (filled($note))
                    <p class="areas-note">{{ $note }}</p>
                @endif
            </div>
        </div>
    </section>
@endif
