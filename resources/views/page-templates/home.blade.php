{{-- Optional sections a page leaves out (such as local-reviews on a page published before it existed) are skipped. --}}
@foreach (app(\App\Content\PageTemplateRegistry::class)->definition(\App\Content\Enums\PageTemplate::Home)->sequence as $section)
    @continue(! isset($page->sections[$section]))
    <div data-page-section="{{ $section }}">
        <x-dynamic-component :component="$page->sections[$section]->component()" :attributes="$page->sections[$section]->attributes()" />
    </div>
@endforeach
