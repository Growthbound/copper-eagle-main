<x-layouts.marketing title="Photo gallery" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Photo gallery</h1>
        <p>A responsive, native-dialog project gallery.</p>
    </section>
    <x-blocks.photo-grid.gallery
        heading="Fictional project details."
        :images="[
            ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional technician checking service controls', 'width' => 1600, 'height' => 2845, 'caption' => 'Controls reviewed before work begins.'],
            ['src' => '/images/home/technician-detail.jpg', 'alt' => 'Fictional technician inspecting an equipment component', 'width' => 1600, 'height' => 2845, 'caption' => 'A closer equipment inspection.'],
            ['src' => '/images/home/technician-maintenance.jpg', 'alt' => 'Fictional technician completing equipment maintenance', 'width' => 1800, 'height' => 3200, 'caption' => 'The documented service scope in progress.'],
        ]"
    />
</x-layouts.marketing>
