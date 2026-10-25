<x-layouts.marketing title="Pricing factors" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Pricing factors</h1>
        <p>A quote-explanation block that never invents a public price.</p>
    </section>
    <x-blocks.pricing.factors
        heading="What can shape a fictional quote?"
        body="Every fictional visit starts with an explanation, so nothing about the cost is a surprise."
        :image="[
            'src' => '/images/brand/photos/drain.jpg',
            'alt' => 'Water running into a sink drain',
            'width' => 1200,
            'height' => 800,
        ]"
        :factors="[
            ['label' => 'System size', 'description' => 'The number of systems and accessible components can change the work involved.'],
            ['label' => 'Current condition', 'description' => 'The observed condition helps define the appropriate scope and equipment.'],
            ['label' => 'Access', 'description' => 'Safe access to agreed components can affect setup and service time.'],
            ['label' => 'Approved add-ons', 'description' => 'Only separately explained and approved work should change the agreed quote.'],
        ]"
    />
</x-layouts.marketing>
