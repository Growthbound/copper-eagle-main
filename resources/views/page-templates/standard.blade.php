@if (! $page->hasBlockCategory('hero'))
    <x-site-header />

    <header class="managed-page-title section-space">
        <h1>{{ $page->title }}</h1>
    </header>
@endif

@foreach ($page->blocks as $block)
    <x-dynamic-component :component="$block->component()" :attributes="$block->attributes()" />
@endforeach
