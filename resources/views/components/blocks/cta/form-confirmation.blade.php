@props([
    'id' => 'form-confirmation',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'steps' => [],
])

@php
    $validSteps = app(\App\Content\BlockData::class)->strings($steps);
@endphp

@if ($validSteps !== [])
    <section id="{{ $id }}" class="form-confirmation-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="local-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
        </header>
        <ol>
            @foreach ($validSteps as $step)
                <li>
                    <span aria-hidden="true">{{ $loop->iteration }}</span>
                    <p>{{ $step }}</p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
