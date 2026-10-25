<x-layouts.marketing title="Service area grid" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Service area grid</h1>
        <p>A crawlable internal-link system for mobile service coverage.</p>
    </section>
    <x-blocks.service-areas.grid
        heading="Fictional communities served."
        :areas="[
            ['name' => 'North Valley', 'href' => '/areas/north-valley', 'note' => 'Mobile service throughout the surrounding community.'],
            ['name' => 'West Ridge', 'href' => '/areas/west-ridge', 'note' => 'Availability confirmed during scheduling.'],
            ['name' => 'Pine Hollow', 'href' => '/areas/pine-hollow', 'note' => 'Service-area page example.'],
            ['name' => 'Cedar Heights', 'href' => '/areas/cedar-heights', 'note' => 'Service-area page example.'],
        ]"
    />
</x-layouts.marketing>
