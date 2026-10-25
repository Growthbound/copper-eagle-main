{{--
    The one place a phone number becomes a link.

    Every call affordance on the site renders through here so the number always
    comes from config('site.phone') — which means a call-tracking number can be
    swapped in one place, and a number the owner has not set never appears at
    all. Blocks must not hand-write tel: links.
--}}
@props([
    'class' => null,
    'label' => null,
    'prefix' => null,
])

@php
    $phone = trim((string) config('site.phone'));
    // tel: wants the dialable digits; the label keeps the readable formatting.
    $dialable = preg_replace('/[^0-9+]/', '', $phone);

    if ($dialable !== '' && ! str_starts_with($dialable, '+')) {
        $dialable = '+1'.ltrim($dialable, '1');
    }
@endphp

@if ($phone !== '' && $dialable !== '')
    <a @class(['call-link', $class => filled($class)]) href="tel:{{ $dialable }}">
        @if (filled($prefix))
            <span class="call-link-prefix">{{ $prefix }}</span>
        @endif
        {{ $label ?? $phone }}
    </a>
@endif
