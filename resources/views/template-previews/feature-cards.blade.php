@php
    $photo = fn (string $file, string $alt, int $width, int $height): array => [
        'src' => '/images/brand/photos/'.$file,
        'alt' => $alt,
        'width' => $width,
        'height' => $height,
    ];
    $twoParagraphs = "A sample first paragraph about finding the leak before anything is opened up.\n\nA second fictional paragraph, so a longer card shows how its paragraphs space out.";
@endphp

<x-layouts.marketing title="Feature cards" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="feature-cards-preview-title">
        <p class="eyebrow text-accent">Fictional local template</p>
        <h1 id="feature-cards-preview-title">Feature cards</h1>
        <p>Two to six round photo cards, beside a large heading over the eagle watermark or under a centred one. A blank line in a card body starts a new paragraph. Every card here is fictional.</p>
    </section>

    <x-blocks.content.feature-cards
        id="features-side"
        heading="The calmest part of a fictional visit."
        emphasis="fictional visit."
        :items="[
            ['title' => 'Explained before it starts.', 'body' => 'A sample card body: the plumber describes what they found and what the fix involves before any work begins.', 'image' => $photo('bath-sink.jpg', 'Modern bathroom sink with a wall-mounted faucet', 900, 900)],
            ['title' => 'Clear, upfront options.', 'body' => 'Fictional copy about hearing the choices and what each one involves, so the homeowner decides.', 'image' => $photo('kitchen.jpg', 'Bright kitchen with copper pans above the counter', 900, 900)],
            ['title' => 'Careful in your home.', 'body' => 'Floors covered, the work area kept tidy, and everything put back where it was.', 'image' => $photo('valve.jpg', 'Stainless steel valve and gauge on a water line', 900, 900)],
        ]"
    />

    <x-blocks.content.feature-cards
        id="features-stacked"
        heading="Our fictional drain services"
        layout="stacked"
        background="stone"
        :items="[
            ['title' => 'Drain cleaning', 'body' => 'Sample copy about clearing buildup so water runs freely again.', 'image' => $photo('drain.jpg', 'Water running into a sink drain', 1200, 800)],
            ['title' => 'Clog and blockage removal', 'body' => 'Finding the blockage at its source and clearing it, with a longer fictional sentence to show how uneven card lengths line up.', 'image' => $photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900)],
            ['title' => 'Pipe repair and replacement', 'body' => 'Repairing or replacing a damaged section of pipe.', 'image' => $photo('copper-pipe.jpg', 'Copper pipe joint against a dark background', 1200, 800)],
            ['title' => 'Preventive maintenance', 'body' => 'A periodic check that keeps small problems small.', 'image' => $photo('valve.jpg', 'Valve and gauge on a water line', 900, 900)],
        ]"
    />

    <x-blocks.content.feature-cards
        id="features-six"
        heading="Six fictional services, in rows of three"
        layout="stacked"
        background="stone"
        :items="[
            ['title' => 'Pipe repairs', 'body' => $twoParagraphs, 'image' => $photo('copper-pipe.jpg', 'Copper pipe joint against a dark background', 1200, 800)],
            ['title' => 'Tub and shower', 'body' => 'Sample copy about drains and valves behind the tile.', 'image' => $photo('bath-sink.jpg', 'Bathroom sink and faucet', 900, 900)],
            ['title' => 'Leaking faucets', 'body' => 'Replacing the worn part that causes the drip.', 'image' => $photo('kitchen.jpg', 'Kitchen counter with copper pans', 900, 900)],
            ['title' => 'Toilets', 'body' => 'A fictional card about repairs and replacements.', 'image' => $photo('drain.jpg', 'Water running into a sink drain', 1200, 800)],
            ['title' => 'Main water lines', 'body' => 'Sample copy about the line that brings water to the home.', 'image' => $photo('valve.jpg', 'Valve and gauge on a water line', 900, 900)],
            ['title' => 'Fixtures', 'body' => 'Fitting sinks, faucets, and showers.', 'image' => $photo('bath-sink.jpg', 'Modern bathroom sink with a wall-mounted faucet', 900, 900)],
        ]"
    />

    <x-blocks.content.feature-cards
        id="features-pair"
        eyebrow="Two cards, no watermark"
        heading="A shorter fictional pair"
        body="An optional paragraph under the heading, for a section that needs a sentence of context before the cards."
        :watermark="false"
        :items="[
            ['title' => 'On time', 'body' => 'Fictional copy for a two-card layout.', 'image' => $photo('kitchen.jpg', 'Kitchen counter with copper pans', 900, 900)],
            ['title' => 'Tidy work', 'body' => 'A second short card body, so the pair reads as a set.', 'image' => $photo('drain.jpg', 'Sink drain with running water', 1200, 800)],
        ]"
    />
</x-layouts.marketing>
