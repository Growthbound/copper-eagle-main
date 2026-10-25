<x-layouts.marketing
    title="Service areas"
    :description="'Where '.config('site.name').' works.'"
>
    <x-blocks.hero.split
        eyebrow="Coverage"
        heading="Service areas"
        :body="'The places '.config('site.name').' serves. If you are just outside one, get in touch anyway.'"
        :cta="['label' => 'Request a visit', 'href' => '#quote']"
    />

    <x-blocks.service-areas.list
        heading="Where we work"
        :areas="$areas->map(fn ($area) => ['label' => $area->label(), 'href' => $area->url()])->all()"
    />

    <x-blocks.cta.quote-form
        eyebrow="Request a visit"
        heading="Tell us about your system."
        body="Share a few details and we'll follow up to confirm the work and the timing."
    />
</x-layouts.marketing>
