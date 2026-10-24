<x-layouts.marketing title="Image split CTA template" :noindex="true">
    <x-blocks.cta.image-split
        id="image-split-cta-preview"
        eyebrow="Image split CTA template — synthetic content only"
        heading="Questions about cleaner air? Our resources can help."
        body="Explore practical homeowner guidance, understand what careful service looks like, and choose the next step that feels right for your home."
        :cta="[
            'label' => 'Explore homeowner resources',
            'href' => '#image-split-cta-preview',
        ]"
        :image="[
            'src' => 'images/home/technician-maintenance.jpg',
            'alt' => 'Technician servicing home equipment',
            'width' => 1800,
            'height' => 3200,
            'fit' => 'cover',
        ]"
    />
</x-layouts.marketing>
