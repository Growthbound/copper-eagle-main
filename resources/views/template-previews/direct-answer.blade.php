<x-layouts.marketing title="Direct answer block" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="direct-answer-preview-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1 id="direct-answer-preview-title">Direct answer block</h1>
        <p>Fictional template content for concise, source-aware answers near the top of search-focused pages.</p>
    </section>

    <x-blocks.content.direct-answer
        id="direct-answer-preview"
        eyebrow="Straight answer"
        heading="What does a professional service visit include?"
        :answer="[
            ['text' => 'A professional service visit typically starts with an inspection of the agreed areas, followed by the work confirmed in the quote and a walkthrough once it is finished. The exact scope depends on the property, its condition, and what was agreed before service, so homeowners should ask what is included and how the home will be protected.'],
        ]"
        :supporting="[
            ['text' => 'This specimen demonstrates a 40–70 word answer followed by concise context, a source, and a relevant next step.'],
        ]"
        :source="[
            'label' => 'Review the fictional source',
            'href' => 'https://example.com/service-source',
        ]"
        :next-step="[
            'label' => 'Explore service templates',
            'href' => '/templates',
        ]"
    />
</x-layouts.marketing>
