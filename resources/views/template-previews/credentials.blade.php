<x-layouts.marketing title="Credentials" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1>Credentials</h1>
        <p>A source-required membership and credential strip.</p>
    </section>
    <x-blocks.logo-strip.credentials
        heading="Fictional credentials, clearly sourced."
        :items="[
            [
                'name' => 'Example clean-air association',
                'image' => ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional association mark placeholder', 'width' => 1600, 'height' => 2845],
                'source_label' => 'Example credential record',
                'source_url' => 'https://example.com/credential-one',
            ],
            [
                'name' => 'Example home-service program',
                'image' => ['src' => '/images/home/technician-detail.jpg', 'alt' => 'Fictional program mark placeholder', 'width' => 1600, 'height' => 2845],
                'source_label' => 'Example membership record',
                'source_url' => 'https://example.com/credential-two',
            ],
        ]"
    />
</x-layouts.marketing>
