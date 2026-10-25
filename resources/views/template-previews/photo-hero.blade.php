@include('template-previews.partials.fictional-services')

<x-layouts.marketing title="Photo hero" :noindex="true">
    <x-blocks.hero.photo
        eyebrow="Fictional home page hero"
        heading="Fictional plumbing help, explained before the work starts."
        emphasis="explained before the work starts"
        body="A sample introduction for the home page. It sits over a full-bleed photo under a deep-blue overlay, stays left-aligned in the page width, and wraps cleanly from a wide desktop down to a phone."
        :primary-action="['label' => 'Contact us today', 'href' => '#photo-hero-notes']"
        :image="['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Water running into a sink drain', 'width' => 1200, 'height' => 800]"
        :video="['src' => '/videos/brand/hero-home.mp4', 'poster' => ['src' => '/videos/brand/hero-home-poster.jpg', 'width' => 1280, 'height' => 720]]"
    />

    <section id="photo-hero-notes" class="marketing-icon-catalog-heading section-space mt-16" aria-labelledby="photo-hero-notes-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h2 id="photo-hero-notes-title">The white chevron rises into the next section.</h2>
        <p>Everything above is fictional. The icon strip shows sample service records bound for this preview only; a live home page reads the service records that have an icon.</p>
        <p>The optional background video plays muted and looping over the photo once the page is idle, with a small pause control in the lower corner. With reduced motion turned on, its poster still shows instead.</p>
    </section>
</x-layouts.marketing>
