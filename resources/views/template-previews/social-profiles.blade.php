<x-layouts.marketing title="Social profiles" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="social-profiles-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="social-profiles-preview-title">Social profiles</h1>
        <p>One image tile per profile, each opening in a new tab, beside an optional contact card such as a vCard QR code. The tiles below are the brand's own artwork, linked to fictional addresses.</p>
    </section>

    <x-blocks.social.profiles
        heading="Find us online"
        :profiles="[
            ['href' => 'https://example.com/sample-tiktok', 'image' => ['src' => '/images/brand/pages/tictok.png', 'alt' => 'Sample company on TikTok', 'width' => 400, 'height' => 408]],
            ['href' => 'https://example.com/sample-nextdoor', 'image' => ['src' => '/images/brand/pages/next-door.png', 'alt' => 'Sample company on Nextdoor', 'width' => 400, 'height' => 353]],
            ['href' => 'https://example.com/sample-facebook', 'image' => ['src' => '/images/brand/pages/face-book.png', 'alt' => 'Sample company on Facebook', 'width' => 400, 'height' => 380]],
            ['href' => 'https://example.com/sample-reviews', 'image' => ['src' => '/images/brand/pages/google-reviews.png', 'alt' => 'Sample company reviews', 'width' => 400, 'height' => 319]],
            ['href' => 'https://example.com/sample-instagram', 'image' => ['src' => '/images/brand/pages/instagram.png', 'alt' => 'Sample company on Instagram', 'width' => 400, 'height' => 403]],
        ]"
        :card="['label' => 'Save the sample contact card', 'href' => '#social-profiles', 'image' => ['src' => '/images/brand/pages/vcard-qr-3.png', 'alt' => 'Sample QR code', 'width' => 220, 'height' => 220]]"
    />
</x-layouts.marketing>
