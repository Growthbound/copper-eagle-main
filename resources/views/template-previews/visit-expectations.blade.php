<x-layouts.marketing title="Visit expectations" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Visit expectations</h1>
        <p>A calm, icon-governed explanation of the service experience.</p>
    </section>
    <x-blocks.cta.visit-expectations
        heading="What to expect from a fictional visit."
        :items="[
            ['label' => 'Confirmed arrival window', 'description' => 'The team communicates timing before the visit.', 'icon' => 'clock'],
            ['label' => 'Scope walkthrough', 'description' => 'The proposed work is explained before service begins.', 'icon' => 'clipboard-document-check'],
            ['label' => 'Home protection', 'description' => 'The team prepares the agreed work area with care.', 'icon' => 'shield-check'],
            ['label' => 'Completion review', 'description' => 'The documented work is reviewed before the team leaves.', 'icon' => 'check-circle'],
        ]"
    />
</x-layouts.marketing>
