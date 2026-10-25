@props(['segments' => []])

@php
    $normalizedSegments = app(\App\Content\RichContent::class)->normalizeSegments($segments);
@endphp

@foreach ($normalizedSegments as $segment)
    @php
        $href = $segment['href'] ?? null;
        $external = is_string($href) && preg_match('#^https?://#i', $href) === 1;
        $decorated = $href || ($segment['mark'] ?? 'plain') !== 'plain';
        $text = $segment['text'];
        $leading = $decorated && preg_match('/^\s+/u', $text, $match) === 1 ? $match[0] : '';
        $trailing = $decorated && preg_match('/\s+$/u', $text, $match) === 1 ? $match[0] : '';
        $visibleText = $decorated ? trim($text) : $text;
    @endphp
    {{ $leading }}
    @if ($href)
        <a href="{{ $href }}" @if ($external) rel="noopener noreferrer" @endif>
    @endif
    @switch($segment['mark'] ?? 'plain')
        @case('strong')
            <strong>{{ $visibleText }}</strong>
            @break
        @case('emphasis')
            <em>{{ $visibleText }}</em>
            @break
        @case('code')
            <code>{{ $visibleText }}</code>
            @break
        @default
            {{ $visibleText }}
    @endswitch
    @if ($href)
        </a>
    @endif
    {{ $trailing }}
@endforeach
