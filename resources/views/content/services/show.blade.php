<x-layouts.marketing
    :title="$service->name()"
    :description="$service->summary()"
    :schema="[
        App\Support\StructuredData::service($service),
        App\Support\StructuredData::faqPage($service),
        App\Support\StructuredData::breadcrumbs([
            ['name' => 'Services', 'url' => route('services.index')],
            ['name' => $service->name(), 'url' => $service->url()],
        ]),
    ]"
>
    <x-blocks.hero.split
        eyebrow="Service"
        :heading="$service->name()"
        :body="$service->summary()"
        :cta="['label' => 'Request a visit', 'href' => '#quote']"
        :image="$service->image()"
    />

    @if ($service->description() || $service->highlights())
        <x-blocks.content.split
            id="about"
            :heading="'About '.$service->name()"
            :body="$service->description()"
            :checklist="$service->highlights()"
        />
    @endif

    @if ($areas->isNotEmpty())
        <x-blocks.service-areas.list
            :heading="$service->name().' near you'"
            eyebrow="Service areas"
            :areas="$areas->map(fn ($area) => [
                'label' => $area->label(),
                'href' => $service->urlInArea($area),
            ])->all()"
        />
    @endif

    <x-blocks.testimonials.quotes :service="$service" eyebrow="In their words" />

    <x-blocks.faq.accordion :service="$service" eyebrow="Before you book" />

    <x-blocks.cta.quote-form
        eyebrow="Request a visit"
        :heading="'Book '.strtolower($service->name()).'.'"
        body="Share a few details and we'll follow up to confirm the work and the timing."
    />
</x-layouts.marketing>
