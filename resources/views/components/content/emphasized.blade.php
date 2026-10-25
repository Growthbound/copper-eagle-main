{{--
    Heading text with one emphasized phrase, the brand's only heading accent
    ("Typography" in docs/brand/design-system.md).

    `emphasis` must be an exact substring of `text`; anything else renders the
    plain text. Both are escaped, so managed content can never inject markup.
    `tone` is "strong" (weight 600, the default) or "accent" (copper).
--}}
@props([
    'text',
    'emphasis' => null,
    'tone' => 'strong',
])

@php
    $text = (string) $text;
    $emphasis = is_string($emphasis) ? trim($emphasis) : '';
    $position = $emphasis !== '' ? mb_strpos($text, $emphasis) : false;
@endphp

@if ($position === false){{ $text }}@else{{ mb_substr($text, 0, $position) }}<strong @class(['heading-emphasis', 'heading-emphasis--accent' => $tone === 'accent'])>{{ $emphasis }}</strong>{{ mb_substr($text, $position + mb_strlen($emphasis)) }}@endif