<x-layouts.marketing
    :title="$service->name().' in '.$area->name()"
    :description="$area->summary() ?? ($service->summary() ?? null)"
    :schema="[
        App\Support\StructuredData::service($service, $area),
        App\Support\StructuredData::faqPage($service, $area),
        App\Support\StructuredData::breadcrumbs([
            ['name' => 'Services', 'url' => route('services.index')],
            ['name' => $service->name(), 'url' => $service->url()],
            ['name' => $area->name(), 'url' => $service->urlInArea($area)],
        ]),
    ]"
>
    <x-blocks.hero.split
        :eyebrow="$area->label()"
        :heading="$service->name().' in '.$area->name()"
        :body="$area->summary() ?? $service->summary()"
        :cta="['label' => 'Request a visit', 'href' => '#quote']"
        :image="$service->image()"
    />

    @if ($service->description() || $service->highlights())
        <x-blocks.content.split
            id="about"
            :heading="'What '.strtolower($service->name()).' involves'"
            :body="$service->description()"
            :checklist="$service->highlights()"
        />
    @endif

    @if ($area->landmarks())
        <x-blocks.content.capability-strip
            :label="'Neighbourhoods we serve in '.$area->name()"
            :items="$area->landmarks()"
        />
    @endif

    @if ($otherAreas->isNotEmpty())
        <x-blocks.service-areas.list
            :heading="'We also cover'"
            eyebrow="Nearby"
            :areas="$otherAreas->map(fn ($other) => [
                'label' => $other->label(),
                'href' => $service->urlInArea($other),
            ])->all()"
        />
    @endif

    <x-blocks.testimonials.quotes :service="$service" :area="$area" eyebrow="In their words" />

    <x-blocks.faq.accordion :service="$service" :area="$area" eyebrow="Before you book" />

    <x-blocks.cta.quote-form
        eyebrow="Request a visit"
        :heading="'Book '.strtolower($service->name()).' in '.$area->name().'.'"
        body="Share a few details and we'll follow up to confirm the work and the timing."
    />
</x-layouts.marketing>
