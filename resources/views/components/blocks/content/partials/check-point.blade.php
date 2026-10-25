{{-- One content/split checklist item: a plain point, or a bold label above its text. --}}
@if ($point['label'] !== null)
    <li><x-check-icon class="check-icon-small" /><span class="check-list-copy"><strong class="check-list-label">{{ $point['label'] }}</strong> {{ $point['text'] }}</span></li>
@else
    <li><x-check-icon class="check-icon-small" />{{ $point['text'] }}</li>
@endif
