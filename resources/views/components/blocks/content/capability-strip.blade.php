@props([
    'label' => 'Capabilities',
    'items' => [],
])

<section class="capability-strip" aria-label="{{ $label }}">
    @foreach ($items as $item)
        <span>{{ $item }}</span>
    @endforeach
</section>
