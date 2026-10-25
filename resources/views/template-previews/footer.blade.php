@php
    /*
     * Request-scoped preview data only. These values demonstrate every
     * optional footer region without writing settings or claiming they are
     * the business's public contact details.
     */
    config()->set('site.address', '123 Example Street, Springfield, ST 00000');
    config()->set('site.email', 'hello@example.test');
    config()->set('site.phone', '+1 555 555 0100');
    config()->set('site.social_links', [
        ['label' => 'Facebook', 'url' => 'https://facebook.com/example'],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/example'],
        ['label' => 'YouTube', 'url' => 'https://youtube.com/@example'],
    ]);

    $companyLinks = [
        ['label' => 'About', 'href' => '/#about'],
        ['label' => 'Services', 'href' => '/services'],
        ['label' => 'Service areas', 'href' => '/areas'],
        ['label' => 'Contact', 'href' => '/#quote'],
    ];

    $resourceLinks = [
        ['label' => 'Homeowner notes', 'href' => '/#resources'],
        ['label' => 'Site summary', 'href' => '/llms.txt'],
        ['label' => 'Sitemap', 'href' => '/sitemap.xml'],
    ];
@endphp

<x-layouts.marketing title="Structured footer template" :noindex="true" :show-footer="false">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="footer-preview-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1 id="footer-preview-title">Structured footer template</h1>
        <p>Template example — contact details are local preview data</p>
    </section>

    <x-site-footers.structured
        :company-links="$companyLinks"
        :resource-links="$resourceLinks"
    />
</x-layouts.marketing>
