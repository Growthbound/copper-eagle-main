<x-layouts.marketing title="Table of contents" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Table of contents</h1>
        <p>A crawlable orientation block for long-form pillar pages.</p>
    </section>
    <x-blocks.content.table-of-contents
        heading="Explore this fictional guide."
        :items="[
            ['label' => 'Start with the direct answer', 'href' => '#direct-answer'],
            ['label' => 'Understand the service scope', 'href' => '#service-scope'],
            ['label' => 'Review the evidence', 'href' => '#evidence'],
            ['label' => 'Choose a practical next step', 'href' => '#next-step'],
        ]"
    />
</x-layouts.marketing>
