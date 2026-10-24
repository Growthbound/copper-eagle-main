@php
    $kind = $preview->kind();
    $page = $preview->page();
    $schema = $page === null ? [] : App\Support\StructuredData::managedPage($page);
    $templateView = $page === null
        ? null
        : app(App\Content\PageTemplateRegistry::class)->definition($page->template)->view;
    $footerLinks = $kind === App\Content\Enums\ContentKind::Footer
        ? $preview->footer()
        : null;
@endphp

<x-layouts.marketing
    :title="$preview->title()"
    :description="$preview->description()"
    :noindex="true"
    :canonical="false"
    :schema="$schema"
    :footer-links="$footerLinks"
    :tracking="false"
>
    @if ($page !== null)
        <x-content.breadcrumbs :items="$page->breadcrumbs" />
        @include($templateView, ['page' => $page])
    @elseif ($kind === App\Content\Enums\ContentKind::Navigation)
        <section class="surface-green">
            <div class="content-container">
                <x-site-header :navigation="$preview->navigation()" />
                <div class="template-preview-intro">
                    <p class="eyebrow">Private site-structure preview</p>
                    <h1>{{ $preview->title() }}</h1>
                    <p>This header is rendering the candidate navigation revision in its public site context.</p>
                </div>
            </div>
        </section>
    @elseif ($kind === App\Content\Enums\ContentKind::Footer)
        <section class="surface-green">
            <div class="content-container">
                <x-site-header />
                <div class="template-preview-intro">
                    <p class="eyebrow">Private site-structure preview</p>
                    <h1>{{ $preview->title() }}</h1>
                    <p>The candidate footer revision is rendered at the bottom of this page in its public site context.</p>
                </div>
            </div>
        </section>
    @endif
</x-layouts.marketing>
