@include('template-previews.partials.fictional-services')

<x-layouts.marketing title="Service icon strip" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="service-strip-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="service-strip-preview-title">Service icon strip</h1>
        <p>Every service record with an icon, in record order, linking to its service page. On the home page the photo hero renders it under the header. These sample records exist only for this preview.</p>
    </section>

    <x-blocks.service-cards.icon-strip />

    <section class="marketing-icon-catalog-heading section-space mt-20" aria-labelledby="service-strip-selection-title">
        <h2 id="service-strip-selection-title">A deliberate selection</h2>
        <p>Pass service record slugs to show a chosen few, in the order given.</p>
    </section>

    <x-blocks.service-cards.icon-strip
        id="service-strip-selection"
        :services="['sample-drain-cleaning', 'sample-water-heaters', 'sample-gas-lines']"
    />
</x-layouts.marketing>
