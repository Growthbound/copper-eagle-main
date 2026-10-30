<x-layouts.marketing title="Job check-ins" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="check-ins-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="check-ins-preview-title">Job check-ins</h1>
        <p>The team's own job write-ups with photos, shown on the home page as "Local Reviews". Two show at first; "Load more check-ins" reveals the rest. These are not customer reviews.</p>
    </section>

    <x-blocks.evidence.check-ins
        heading="Local Reviews"
        :items="app(App\Support\CopperEagleTemplateContent::class)->sampleCheckIns()"
    />
</x-layouts.marketing>
