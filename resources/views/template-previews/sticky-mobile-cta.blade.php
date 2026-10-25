<x-layouts.marketing title="Sticky mobile CTA" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Sticky mobile CTA</h1>
        <p>Resize below the mobile breakpoint to inspect the safe-area-aware action.</p>
    </section>
    <section class="marketing-icon-catalog-heading section-space">
        <h2>Fictional page content above the action.</h2>
        <p>The sticky control appears only at small viewport sizes and only with a safe destination.</p>
    </section>
    <x-blocks.cta.sticky-mobile :primary-action="['label' => 'Request a fictional visit', 'href' => '#quote']" />
</x-layouts.marketing>
