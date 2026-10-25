<x-layouts.marketing title="Proof statistics" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Proof statistics</h1>
        <p>A source-required statistics specimen.</p>
    </section>
    <x-blocks.stats.proof
        heading="Fictional numbers with receipts."
        :items="[
            ['value' => '42', 'label' => 'Documented fictional projects', 'context' => 'Example reporting period only.', 'source_label' => 'Example project report', 'source_url' => 'https://example.com/project-report'],
            ['value' => '7', 'label' => 'Fictional service areas reviewed', 'context' => 'Example coverage record only.', 'source_label' => 'Example coverage record', 'source_url' => 'https://example.com/coverage'],
            ['value' => '100%', 'label' => 'Fictional records with a named source', 'context' => 'A template-system demonstration.', 'source_label' => 'Example evidence register', 'source_url' => 'https://example.com/evidence'],
        ]"
    />
</x-layouts.marketing>
