<x-layouts.marketing
    title="Our services"
    :description="'What '.config('site.name').' does, and where.'"
>
    <x-blocks.hero.split
        eyebrow="What we do"
        heading="Our services"
        :body="'Every service '.config('site.name').' offers, with what to expect from each visit.'"
        :cta="['label' => 'Request a visit', 'href' => '#quote']"
    />

    <x-blocks.service-cards.grid
        heading="Where we can help"
        eyebrow="Focused options"
        :services="$services->map(fn ($service) => [
            'title' => $service->name(),
            'body' => $service->summary() ?? '',
            'href' => $service->url(),
            'image' => $service->image(),
        ])->all()"
    />

    <x-blocks.cta.quote-form
        eyebrow="Request a visit"
        heading="Tell us about your system."
        body="Share a few details and we'll follow up to confirm the work and the timing."
    />
</x-layouts.marketing>
