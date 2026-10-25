<x-layouts.marketing title="CTA panel" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="cta-panel-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="cta-panel-preview-title">CTA panel</h1>
        <p>A dark full-width band. The button is optional: the copper guarantee band is a statement with no button and no call line. A panel may carry a small logo above its heading.</p>
    </section>

    <x-blocks.cta.panel
        id="cta-panel-guarantee"
        eyebrow="Sample promise eyebrow"
        heading="Sample guarantee headline"
        body="A sample promise about the work, with no button, like the live guarantee band."
        tone="copper"
    />

    <x-blocks.cta.panel
        id="cta-panel-review"
        heading="Your fictional review is next!"
        body="A sample invitation to leave a review, with a small logo above the heading."
        :cta="['label' => 'Leave a sample review', 'href' => '#cta-panel-review']"
        :logo="['src' => '/images/brand/pages/google-g-icon-download.png', 'alt' => 'Google logo', 'width' => 512, 'height' => 512]"
        :image="['src' => '/images/brand/photos/copper-pipe.jpg', 'alt' => 'Copper pipe joint', 'width' => 1200, 'height' => 800]"
    />
</x-layouts.marketing>
