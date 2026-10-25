{{--
    Plain managed text as one or more escaped paragraphs. A blank line starts
    a new <p>; a single line break stays inside its paragraph. The text is
    never treated as HTML. `class`, when given, goes on every paragraph.
--}}
@props([
    'text' => null,
    'class' => null,
])

@php
    $paragraphs = is_string($text)
        ? array_values(array_filter(
            array_map('trim', preg_split('/\R[ \t]*\R\s*/u', trim($text)) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ))
        : [];
@endphp

@foreach ($paragraphs as $paragraph)
    @if (filled($class))
        <p class="{{ $class }}">{{ $paragraph }}</p>
    @else
        <p>{{ $paragraph }}</p>
    @endif
@endforeach
