@php
    use App\Content\Enums\PageTemplate;
    use App\Content\PageTemplateRegistry;
    use App\Support\TemplatePreviewContent;

    $template = PageTemplate::ServicePillar;
    $preview = app(TemplatePreviewContent::class)->page($template, 'Service pillar — Fictional complete page');
    $definition = app(PageTemplateRegistry::class)->definition($template);
@endphp

<x-layouts.marketing
    :title="$preview['title']"
    :description="$preview['description']"
    :noindex="true"
    :canonical="false"
>
    @foreach ($definition->sequence as $section)
        <div data-page-section="{{ $section }}">
            <x-dynamic-component
                :component="$preview['sections'][$section]->component()"
                :attributes="$preview['sections'][$section]->attributes()"
            />
        </div>
    @endforeach
</x-layouts.marketing>
