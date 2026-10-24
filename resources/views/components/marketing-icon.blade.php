@props(['name'])

@php
    $component = App\Support\MarketingIcon::component($name);
@endphp

<x-dynamic-component
    :component="$component"
    {{ $attributes->class('marketing-icon') }}
    aria-hidden="true"
    focusable="false"
/>
