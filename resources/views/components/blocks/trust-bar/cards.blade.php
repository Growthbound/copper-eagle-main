@props([
    'label' => 'Service standards',
    'items' => [],
])

<section class="trust-grid" aria-label="{{ $label }}">
    @foreach ($items as $item)
        <article class="trust-card">
            <x-check-icon />
            <h2>{{ $item['title'] }}</h2>
            <p>{{ $item['body'] }}</p>
        </article>
    @endforeach
</section>
