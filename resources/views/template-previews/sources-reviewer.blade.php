<x-layouts.marketing title="Sources and reviewer" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Sources and reviewer</h1>
        <p>An accountability block for evidence, ownership, and freshness.</p>
    </section>
    <x-blocks.content.sources-reviewer
        heading="How this fictional page was checked."
        :sources="[
            ['label' => 'Example technical reference', 'publisher' => 'Fictional standards group', 'url' => 'https://example.com/reference', 'reviewed_at' => '2026-07-20'],
            ['label' => 'Example service record', 'publisher' => 'Fictional internal record', 'url' => 'https://example.com/record', 'reviewed_at' => '2026-07-21'],
        ]"
        :reviewer="[
            'name' => 'Jordan Example',
            'role' => 'Fictional technical reviewer',
            'reviewed_at' => '2026-07-22',
        ]"
    />
</x-layouts.marketing>
