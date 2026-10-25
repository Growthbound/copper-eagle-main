<x-layouts.marketing
    :title="config('site.name').' in '.$area->name()"
    :description="$area->summary()"
    :schema="[
        App\Support\StructuredData::faqPage(null, $area),
        App\Support\StructuredData::breadcrumbs([
            ['name' => 'Service areas', 'url' => route('areas.index')],
            ['name' => $area->label(), 'url' => $area->url()],
        ]),
    ]"
>
    <x-blocks.hero.split
        :eyebrow="$area->label()"
        :heading="config('site.name').' in '.$area->name()"
        :body="$area->summary()"
        :cta="['label' => 'Request a visit', 'href' => '#quote']"
    />

    @if ($area->landmarks())
        <x-blocks.content.capability-strip
            :label="'Neighbourhoods we serve in '.$area->name()"
            :items="$area->landmarks()"
        />
    @endif

    @if ($services->isNotEmpty())
        <x-blocks.service-areas.list
            :heading="'What we do in '.$area->name()"
            eyebrow="Services"
            :areas="$services->map(fn ($service) => [
                'label' => $service->name(),
                'href' => $service->urlInArea($area),
            ])->all()"
        />
    @endif

    <x-blocks.testimonials.quotes :area="$area" eyebrow="In their words" />

    <x-blocks.faq.accordion :area="$area" eyebrow="Before you book" />

    <x-blocks.cta.quote-form
        eyebrow="Request a visit"
        :heading="'Book a visit in '.$area->name().'.'"
        body="Share a few details and we'll follow up to confirm the work and the timing."
    />
</x-layouts.marketing>
