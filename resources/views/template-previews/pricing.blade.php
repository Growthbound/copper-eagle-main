@php
    $plans = [
        [
            'name' => 'Starter example',
            'summary' => 'Template example — not live pricing',
            'prices' => [
                'monthly' => ['amount' => '$120', 'suffix' => '/ month', 'note' => 'Example only'],
                'yearly' => ['amount' => '$1,200', 'suffix' => '/ year', 'note' => 'Example only'],
            ],
            'featureHeading' => 'Example features',
            'features' => ['First example feature', 'Second example feature', 'Third example feature'],
            'cta' => ['label' => 'Example action', 'href' => '#template-end'],
        ],
        [
            'name' => 'Complete example',
            'summary' => 'Template example — not live pricing',
            'prices' => [
                'monthly' => ['amount' => '$240', 'suffix' => '/ month', 'note' => 'Example only'],
                'yearly' => ['amount' => '$2,400', 'suffix' => '/ year', 'note' => 'Example only'],
            ],
            'featureHeading' => 'Example features',
            'features' => ['Everything in the first example', 'Additional example feature'],
            'cta' => ['label' => 'Example action', 'href' => '#template-end'],
            'featured' => true,
        ],
        [
            'name' => 'Custom example',
            'summary' => 'Template example — not live pricing',
            'prices' => [
                'monthly' => ['amount' => 'Request an estimate', 'suffix' => null, 'note' => null],
                'yearly' => ['amount' => 'Request an estimate', 'suffix' => null, 'note' => null],
            ],
            'featureHeading' => 'Example features',
            'features' => ['Custom example scope', 'Flexible example CTA'],
            'cta' => ['label' => 'Example action', 'href' => '#template-end'],
        ],
    ];
@endphp

<x-layouts.marketing title="Pricing sheet template gallery" :noindex="true">
    <div class="section-heading left">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1>Pricing sheet template gallery</h1>
        <p>Template example — not live pricing</p>
    </div>

    <x-blocks.pricing.monthly
        id="pricing-monthly"
        eyebrow="Variant one"
        heading="Monthly-only template"
        body="One visible price mode with no selector."
        :plans="$plans"
    />

    <x-blocks.pricing.yearly
        id="pricing-yearly"
        eyebrow="Variant two"
        heading="Yearly-only template"
        body="One visible price mode with no selector."
        badge="Example yearly badge"
        :plans="$plans"
    />

    <x-blocks.pricing.toggle
        id="pricing-toggle"
        eyebrow="Variant three"
        heading="Monthly and yearly toggle template"
        body="Two accessible price modes using the same cards."
        default-mode="yearly"
        :modes="[
            'monthly' => ['label' => 'Monthly', 'badge' => null],
            'yearly' => ['label' => 'Yearly', 'badge' => 'Example saving'],
        ]"
        :plans="$plans"
    />

    <div id="template-end"></div>
</x-layouts.marketing>
