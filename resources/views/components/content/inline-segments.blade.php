@props(['segments' => []])

{{--
    Each segment renders on one unindented line, with nothing between the
    tags: template whitespace would otherwise reach the page as a space before
    punctuation ("reply STOP .") and as an underlined space inside links.
    Spaces in the segment text itself are kept, outside any link or mark.
--}}
@php
    $normalizedSegments = app(\App\Content\RichContent::class)->normalizeSegments($segments);
@endphp
@foreach ($normalizedSegments as $segment)
@php
    $href = $segment['href'] ?? null;
    $external = is_string($href) && preg_match('#^https?://#i', $href) === 1;
    $mark = $segment['mark'] ?? 'plain';
    $decorated = $href || $mark !== 'plain';
    $text = $segment['text'];
    $leading = $decorated && preg_match('/^\s+/u', $text, $match) === 1 ? $match[0] : '';
    $trailing = $decorated && preg_match('/\s+$/u', $text, $match) === 1 ? $match[0] : '';
    $visibleText = $decorated ? trim($text) : $text;
@endphp
{{ $leading }}@if ($href)<a href="{{ $href }}"@if ($external) rel="noopener noreferrer"@endif>@endif
@if ($mark === 'strong')<strong>{{ $visibleText }}</strong>@elseif ($mark === 'emphasis')<em>{{ $visibleText }}</em>@elseif ($mark === 'code')<code>{{ $visibleText }}</code>@else{{ $visibleText }}@endif
@if ($href)</a>@endif{{ $trailing }}@endforeach
