<x-layouts.marketing title="Interior hero" :noindex="true">
    <x-blocks.hero.interior
        eyebrow="Fictional company page"
        heading="Interior hero for a helpful, human story."
        body="A focused introduction for about, process, contact, and other standard interior pages."
        :primary-action="['label' => 'Request a fictional visit', 'href' => '#quote']"
        :secondary-action="['label' => 'Explore templates', 'href' => '/templates']"
        :image="[
            'src' => '/images/brand/photos/copper-pipe.jpg',
            'alt' => 'Copper pipe joint against a dark background',
            'width' => 1200,
            'height' => 800,
        ]"
    />

    <x-blocks.content.split
        id="interior-hero-follow"
        heading="The chevron rises into whatever section comes next."
        body="Without a photo the band is plain deep blue. With one, the photo sits under the deep-blue overlay so the white heading keeps its contrast."
        background="mist"
    />
</x-layouts.marketing>
