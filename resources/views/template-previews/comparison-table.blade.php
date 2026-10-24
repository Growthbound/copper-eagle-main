<x-layouts.marketing title="Comparison table" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Comparison table</h1>
        <p>A semantic decision table that stays useful without scripts.</p>
    </section>
    <x-blocks.content.comparison-table
        heading="Compare fictional service approaches."
        caption="Fictional template comparison — not a statement about any provider."
        :columns="[
            ['key' => 'quick', 'label' => 'Quick visit'],
            ['key' => 'careful', 'label' => 'Careful process'],
        ]"
        :rows="[
            ['label' => 'Scope explained first', 'values' => ['quick' => 'Varies', 'careful' => 'Included']],
            ['label' => 'System-specific plan', 'values' => ['quick' => 'Limited', 'careful' => 'Included']],
            ['label' => 'Completion walkthrough', 'values' => ['quick' => 'Varies', 'careful' => 'Included']],
        ]"
    />
</x-layouts.marketing>
