{{--
    Renders a page from content/pages by walking its blocks in order.

    Each block's "type" maps to an anonymous component under
    resources/views/components/blocks, and its "props" are handed over as
    component attributes, so a block declares its own inputs with @props and
    this file never needs to know about any particular block.
--}}
@php
    $managed = $page instanceof App\Content\ManagedPage;
    $schema = $managed
        ? App\Support\StructuredData::managedPage($page)
        : array_filter([
            $page->hasBlockCategory('faq') ? App\Support\StructuredData::faqPage() : null,
        ]);
    $templateView = $managed
        ? app(App\Content\PageTemplateRegistry::class)->definition($page->template)->view
        : 'page-templates.standard';
@endphp

<x-layouts.marketing
    :title="$page->title"
    :description="$page->description"
    :noindex="$page->noindex"
    :canonical="$managed ? $page->canonicalUrl() : true"
    :open-graph="$managed ? [
        'type' => $page->kind === App\Content\Enums\ContentKind::Article ? 'article' : 'website',
        'title' => $page->title,
        'description' => $page->description,
        'url' => $page->canonicalUrl(),
        'image' => $page->socialImage,
    ] : []"
    :schema="$schema"
>
    @if ($managed)
        <x-content.breadcrumbs :items="$page->breadcrumbs" />
    @endif
    @include($templateView, ['page' => $page])
</x-layouts.marketing>
