<x-layouts.marketing title="Case study" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Case study</h1>
        <p>A narrative evidence block with sourced facts and disciplined outcome language.</p>
    </section>
    <x-blocks.evidence.case-study
        heading="A fictional residential project."
        problem="Visible debris around several registers prompted the homeowner to ask for a system inspection and a clearly defined cleaning scope."
        work="The fictional team documented the accessible system, explained the agreed work, protected the surrounding area, and completed the approved sections."
        outcome="The team reviewed the completed scope with the homeowner and documented the work without making unsupported health or performance claims."
        :facts="[
            ['label' => 'Project type', 'value' => 'Fictional residential service', 'source_label' => 'Example project record', 'source_url' => 'https://example.com/project'],
            ['label' => 'Scope', 'value' => 'Agreed accessible components', 'source_label' => 'Example work order', 'source_url' => 'https://example.com/work-order'],
        ]"
        :image="['src' => '/images/home/technician-maintenance.jpg', 'alt' => 'Fictional technician completing documented maintenance work', 'width' => 1800, 'height' => 3200]"
    />
</x-layouts.marketing>
