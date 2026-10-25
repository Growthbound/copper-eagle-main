<x-layouts.marketing title="Before and after" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Before and after</h1>
        <p>A sourced comparison specimen with both images visible without JavaScript.</p>
    </section>
    <x-blocks.before-after.slider
        heading="Compare a fictional project."
        :before="[
            'src' => '/images/home/technician-controls.jpg',
            'alt' => 'Fictional before view of equipment controls',
            'width' => 1600,
            'height' => 2845,
            'source_label' => 'Fictional project record',
            'source_url' => 'https://example.com/before-record',
        ]"
        :after="[
            'src' => '/images/home/technician-detail.jpg',
            'alt' => 'Fictional after view of inspected equipment',
            'width' => 1600,
            'height' => 2845,
            'source_label' => 'Fictional project record',
            'source_url' => 'https://example.com/after-record',
        ]"
    />
</x-layouts.marketing>
