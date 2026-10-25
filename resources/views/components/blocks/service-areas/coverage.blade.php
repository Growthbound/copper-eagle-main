@props([
    'id' => 'coverage',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'coverageLabel' => 'Illustration of mobile service coverage',
    'areas' => [],
])

@php
    $validAreas = app(\App\Content\BlockData::class)->serviceAreas($areas);
@endphp

@if ($validAreas !== [])
    <section id="{{ $id }}" class="coverage-section edge-panel section-space" aria-labelledby="{{ $id }}-title">
        <div class="coverage-inner">
            <div class="coverage-copy">
                @if (filled($eyebrow))
                    <p class="eyebrow">{{ $eyebrow }}</p>
                @endif
                <h2 id="{{ $id }}-title">{{ $heading }}</h2>
                @if (filled($body))
                    <p>{{ $body }}</p>
                @endif
                <ul>
                    @foreach ($validAreas as $area)
                        <li><a href="{{ $area['href'] }}">{{ $area['name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="coverage-illustration" role="img" aria-label="{{ $coverageLabel }}">
                <span></span><span></span><span></span>
                <x-marketing-icon name="truck" />
            </div>
        </div>
    </section>
@endif
