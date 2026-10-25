<x-layouts.marketing title="Inline CTA" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Inline CTA</h1>
        <p>A centered prompt between sections: white or mist band, copper or light-blue button, one optional underlined phrase.</p>
    </section>

    <x-blocks.cta.inline-band
        heading="Let us help with your fictional project so you can get back to bed."
        emphasis="back to bed."
        :primary-action="['label' => 'Request a fictional visit', 'href' => '#quote']"
    />

    <x-blocks.cta.inline-band
        id="inline-cta-mist"
        heading="Not sure what you need?"
        body="Describe what you are seeing or hearing, and a fictional team member will help you work out the next step."
        background="mist"
        :primary-action="['label' => 'Talk to a fictional plumber', 'href' => '#quote']"
        :secondary-action="['label' => 'Review the process', 'href' => '/templates']"
    />

    <x-blocks.cta.inline-band
        id="inline-cta-sky"
        heading="What can we help you with?"
        button-style="sky"
        :primary-action="['label' => 'Reach out today', 'href' => '#quote']"
    />
</x-layouts.marketing>
