<x-layouts.marketing title="Story checklist template" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="story-checklist-preview-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1 id="story-checklist-preview-title">Story checklist template</h1>
        <p>Template example — not public company copy</p>
    </section>

    <x-blocks.content.story-checklist
        id="story-checklist-preview"
        heading="The story behind careful service."
        body="This preview shows how an editorial company narrative can sit beside overlapping service imagery and concise proof points."
        :items="[
            'Clear communication',
            'Careful workmanship',
            'Respectful service',
            'A cleaner-air goal',
        ]"
        :image="[
            'src' => 'images/home/technician-maintenance.jpg',
            'alt' => 'Technician servicing home equipment',
            'width' => 1800,
            'height' => 3200,
        ]"
        :inset="[
            'src' => 'images/home/technician-detail.jpg',
            'alt' => 'Technician inspecting equipment components',
            'width' => 1600,
            'height' => 2845,
        ]"
    />
</x-layouts.marketing>
