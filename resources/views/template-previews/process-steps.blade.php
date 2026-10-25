@php
    $steps = [
        [
            'icon' => 'magnifying-glass',
            'title' => 'Select your service',
            'body' => 'Choose the example service that best matches the work you need.',
        ],
        [
            'icon' => 'calendar-days',
            'title' => 'Choose your date',
            'body' => 'Pick a sample appointment window that fits your household.',
        ],
        [
            'icon' => 'check-circle',
            'title' => 'Confirm and relax',
            'body' => 'Review the example details and know what happens next.',
        ],
    ];

    $icons = App\Support\MarketingIcon::catalog();
@endphp

<x-layouts.marketing title="Icon process template" :noindex="true">
    <x-blocks.process-steps.icon-grid
        id="process-icon-grid-preview"
        eyebrow="Local template preview"
        heading="Icon process template"
        body="Template example — not public process content"
        :steps="$steps"
    />

    <section
        class="marketing-icon-catalog section-space"
        aria-labelledby="marketing-icon-catalog-title"
    >
        <header class="marketing-icon-catalog-heading">
            <p class="eyebrow text-accent">Agent reference</p>
            <h2 id="marketing-icon-catalog-title">Approved marketing icon catalog</h2>
            <p>
                Use these exact names with
                <code>&lt;x-marketing-icon name="..." /&gt;</code>.
            </p>
        </header>

        <div class="marketing-icon-catalog-grid">
            @foreach ($icons as $name => $guidance)
                <article class="marketing-icon-catalog-card">
                    <x-marketing-icon
                        :name="$name"
                        class="marketing-icon-catalog-symbol"
                    />
                    <h3><code>{{ $name }}</code></h3>
                    <p>{{ $guidance }}</p>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.marketing>
