<x-layouts.marketing title="Coverage map" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Coverage map</h1>
        <p>A mobile-service illustration paired with crawlable place links.</p>
    </section>
    <x-blocks.service-areas.coverage
        eyebrow="Mobile service coverage"
        heading="Coverage map for fictional communities."
        body="This is a service-area illustration, not a storefront location or a claim of a physical office."
        coverage-label="Fictional illustration showing a mobile service team reaching several communities"
        :areas="[
            ['name' => 'North Valley', 'href' => '/areas/north-valley'],
            ['name' => 'West Ridge', 'href' => '/areas/west-ridge'],
            ['name' => 'Pine Hollow', 'href' => '/areas/pine-hollow'],
            ['name' => 'Cedar Heights', 'href' => '/areas/cedar-heights'],
        ]"
    />
</x-layouts.marketing>
