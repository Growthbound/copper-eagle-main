<x-layouts.marketing title="Related content" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Related content</h1>
        <p>Deliberate internal links that help readers continue their decision.</p>
    </section>
    <x-blocks.content.related-content
        heading="Keep exploring fictional resources."
        :items="[
            ['type' => 'Service guide', 'title' => 'How a careful visit works', 'summary' => 'A fictional walkthrough of a calm, clearly scoped service.', 'href' => '/services'],
            ['type' => 'Decision guide', 'title' => 'Questions to ask before booking', 'summary' => 'A fictional checklist for comparing service providers.', 'href' => '/templates'],
            ['type' => 'Local resource', 'title' => 'Understanding service coverage', 'summary' => 'A fictional example of service-area content.', 'href' => '/areas'],
        ]"
    />
</x-layouts.marketing>
