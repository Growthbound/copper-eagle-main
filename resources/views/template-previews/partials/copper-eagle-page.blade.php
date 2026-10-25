{{--
    Renders one Copper Eagle page template with its fictional sample content,
    exactly as a published page would render it. Local-only gallery preview.
    Included with ['template' => PageTemplate case, 'title' => sample H1].

    The sample menu is request-scoped, like the footer preview's contact
    details, so the previews link to each other without touching settings.
--}}
@php
    config()->set('site.navigation', [
        ['label' => 'Services', 'href' => url('/_templates/services-hub-page'), 'children' => [
            ['label' => 'Sample service page', 'href' => url('/_templates/service-detail-page')],
        ]],
        ['label' => '24/7 Emergency', 'href' => url('/_templates/emergency-page')],
        ['label' => 'About', 'href' => url('/_templates/about-page'), 'children' => [
            ['label' => 'Team', 'href' => url('/_templates/team-page')],
            ['label' => 'Reviews', 'href' => url('/_templates/reviews-page')],
        ]],
        ['label' => 'Contact', 'href' => url('/_templates/contact-page')],
    ]);

    $preview = app(App\Support\TemplatePreviewContent::class)->page($template, $title);
    $definition = app(App\Content\PageTemplateRegistry::class)->definition($template);
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
