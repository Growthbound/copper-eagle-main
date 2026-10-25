<x-layouts.marketing title="Form confirmation" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Form confirmation</h1>
        <p>A clear post-submit expectation block.</p>
    </section>
    <x-blocks.cta.form-confirmation
        heading="What happens after a fictional request?"
        body="The sequence removes ambiguity without promising timing that has not been verified."
        :steps="[
            'The request is recorded for the service team.',
            'A team member reviews the details and follows up.',
            'The homeowner confirms the scope and chooses the next step.',
        ]"
    />
</x-layouts.marketing>
