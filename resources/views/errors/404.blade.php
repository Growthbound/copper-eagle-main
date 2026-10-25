{{--
    A visitor who mistypes a URL or follows a stale link is still a customer.
    The framework's default page is a dead end; this one keeps the header, the
    phone number, and a route back into the services.
--}}
<x-layouts.marketing title="Page not found" noindex>
    <x-blocks.hero.split
        eyebrow="Page not found"
        heading="That page has moved or never existed."
        body="Nothing is broken on your end. Pick up from one of these instead, or get in touch and we will point you the right way."
        :cta="['label' => 'Request a visit', 'href' => '/#quote']"
        :secondary="['label' => 'See our services', 'href' => route('services.index')]"
    />

    <x-blocks.service-cards.grid heading="What we do" eyebrow="Services" />
</x-layouts.marketing>
