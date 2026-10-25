@foreach (app(\App\Content\PageTemplateRegistry::class)->definition(\App\Content\Enums\PageTemplate::Team)->sequence as $section)
    <div data-page-section="{{ $section }}">
        <x-dynamic-component :component="$page->sections[$section]->component()" :attributes="$page->sections[$section]->attributes()" />
    </div>
@endforeach
