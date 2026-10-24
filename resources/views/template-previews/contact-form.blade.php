<x-layouts.marketing title="Split contact form template" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="contact-form-preview-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1 id="contact-form-preview-title">Split contact form template</h1>
        <p>Template example — not public contact copy</p>
    </section>

    <x-blocks.cta.quote-form-split
        id="contact-form-preview"
        eyebrow="Request a visit"
        heading="Request a free service quote"
        body="Tell us what your home needs. We’ll follow up with clear next steps and a time that works."
        submit-label="Get a quote"
        :image="[
            'src' => 'images/home/technician-maintenance.jpg',
            'alt' => 'Technician servicing home equipment',
            'width' => 1800,
            'height' => 3200,
        ]"
    />
</x-layouts.marketing>
