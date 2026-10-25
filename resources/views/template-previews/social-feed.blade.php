<x-layouts.marketing title="Social feed" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="social-feed-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="social-feed-preview-title">Social feed</h1>
        <p>Profile handles beside a static grid of recent posts. Each post opens in a new tab; nothing is embedded from the social network.</p>
    </section>

    <x-blocks.social.feed
        heading="Connect with us on social media"
        :profiles="[
            ['label' => 'Instagram', 'handle' => '@sample.company', 'href' => 'https://example.com/sample-profile'],
            ['label' => 'YouTube', 'handle' => '@SampleCompany'],
        ]"
        :posts="[
            ['href' => 'https://example.com/sample-post-1', 'image' => ['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Water running into a sink drain', 'width' => 1200, 'height' => 800]],
            ['href' => 'https://example.com/sample-post-2', 'image' => ['src' => '/images/brand/photos/bath-sink.jpg', 'alt' => 'Bathroom sink and faucet', 'width' => 900, 'height' => 900]],
            ['href' => 'https://example.com/sample-post-3', 'image' => ['src' => '/images/brand/photos/copper-pipe.jpg', 'alt' => 'Copper pipe joint', 'width' => 1200, 'height' => 800]],
            ['href' => 'https://example.com/sample-post-4', 'image' => ['src' => '/images/brand/photos/valve.jpg', 'alt' => 'Valve and gauge on a water line', 'width' => 900, 'height' => 900]],
            ['href' => 'https://example.com/sample-post-5', 'image' => ['src' => '/images/brand/photos/kitchen.jpg', 'alt' => 'Kitchen counter with copper pans', 'width' => 900, 'height' => 900]],
            ['href' => 'https://example.com/sample-post-6', 'image' => ['src' => '/images/home/technician-detail.jpg', 'alt' => 'Fictional technician at work', 'width' => 1600, 'height' => 2845]],
            ['href' => 'https://example.com/sample-post-7', 'image' => ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional technician checking controls', 'width' => 1600, 'height' => 2844]],
            ['href' => 'https://example.com/sample-post-8', 'image' => ['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Water running into a sink drain', 'width' => 1200, 'height' => 800]],
            ['href' => 'https://example.com/sample-post-9', 'image' => ['src' => '/images/brand/photos/bath-sink.jpg', 'alt' => 'Bathroom sink and faucet', 'width' => 900, 'height' => 900]],
            ['href' => 'https://example.com/sample-post-10', 'image' => ['src' => '/images/brand/photos/valve.jpg', 'alt' => 'Valve and gauge on a water line', 'width' => 900, 'height' => 900]],
        ]"
    />
</x-layouts.marketing>
