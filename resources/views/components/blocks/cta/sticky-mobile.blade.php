@props([
    'id' => 'sticky-mobile-cta',
    'primaryAction',
])

@php
    $action = app(\App\Content\BlockData::class)->link($primaryAction);
@endphp

@if ($action)
    <aside id="{{ $id }}" class="sticky-mobile-cta" aria-label="Quick action">
        <a href="{{ $action['href'] }}">{{ $action['label'] }} <span aria-hidden="true">→</span></a>
    </aside>
@endif
