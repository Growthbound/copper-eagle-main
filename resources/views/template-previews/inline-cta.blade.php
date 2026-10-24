<x-layouts.marketing title="Inline CTA" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Inline CTA</h1>
        <p>A full-width conversion band that preserves the shared inner margin.</p>
    </section>
    <x-blocks.cta.inline-band
        eyebrow="A calm next step"
        heading="Ready to discuss a fictional service?"
        body="Ask a question, confirm the scope, and decide what feels right for your home."
        :primary-action="['label' => 'Request a fictional visit', 'href' => '#quote']"
        :secondary-action="['label' => 'Review the process', 'href' => '/templates']"
    />
</x-layouts.marketing>
