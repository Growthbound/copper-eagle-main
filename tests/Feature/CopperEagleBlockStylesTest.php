<?php

namespace Tests\Feature;

use App\Content\BlockRegistry;
use App\Livewire\QuoteForm;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The restyled shared blocks: their optional Copper Eagle props render the
 * live-site variants, fall back to the original look when omitted, and are
 * part of the governed content contract.
 */
class CopperEagleBlockStylesTest extends TestCase
{
    private const PHOTO = [
        'src' => '/images/brand/photos/copper-pipe.jpg',
        'alt' => 'Copper pipe joint',
        'width' => 1200,
        'height' => 800,
    ];

    public function test_interior_hero_renders_an_optional_decorative_backdrop(): void
    {
        $html = Blade::render(
            '<x-blocks.hero.interior eyebrow="After hours" heading="Emergency plumbing" :image="$image" :primary-action="$action" />',
            ['image' => self::PHOTO, 'action' => ['label' => 'Call now', 'href' => '#call']],
        );

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('has-backdrop', $html);
        $this->assertStringContainsString('class="interior-hero-backdrop"', $html);
        $this->assertStringContainsString('src="/images/brand/photos/copper-pipe.jpg"', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="800"', $html);
        $this->assertStringContainsString('class="button button-orange" href="#call"', $html);

        $plain = Blade::render('<x-blocks.hero.interior heading="Plain" />');

        $this->assertStringNotContainsString('interior-hero-backdrop', $plain);
        $this->assertStringNotContainsString('has-backdrop', $plain);

        $unsafe = Blade::render('<x-blocks.hero.interior heading="Unsafe" :image="$image" />', [
            'image' => [...self::PHOTO, 'src' => 'javascript:alert(1)'],
        ]);

        $this->assertStringNotContainsString('interior-hero-backdrop', $unsafe);
        $this->assertStringNotContainsString('javascript:', $unsafe);
    }

    public function test_split_renders_circle_portraits_emphasis_and_a_copper_button(): void
    {
        $html = Blade::render(
            '<x-blocks.content.split id="intro" heading="Finally, a master plumber you can trust." emphasis="master plumber" lead="Bold lead." body="Body copy." image-shape="circle" :image="$image" :inset="$inset" :cta="$cta" />',
            [
                'image' => self::PHOTO,
                'inset' => ['src' => '/images/brand/photos/valve.jpg', 'alt' => 'Badge', 'width' => 900, 'height' => 900],
                'cta' => ['label' => 'Meet the team', 'href' => '/team'],
            ],
        );

        $this->assertStringContainsString('is-image-circle', $html);
        $this->assertStringContainsString('<strong class="heading-emphasis">master plumber</strong>', $html);
        $this->assertStringContainsString('<p class="split-lead">Bold lead.</p>', $html);
        $this->assertStringContainsString('class="image-stack-small"', $html);
        $this->assertStringContainsString('class="button button-orange split-action" href="/team">Meet the team</a>', $html);
        $this->assertStringNotContainsString('split-band', $html);
        $this->assertStringNotContainsString('edge-panel', $html);
        $this->assertLessThan(strpos($html, 'section-copy'), strpos($html, 'image-stack'));
    }

    public function test_split_mirrors_columns_and_paints_its_band(): void
    {
        $html = Blade::render(
            '<x-blocks.content.split heading="Why maintenance matters" body="Body." image-side="end" background="stone" :image="$image" />',
            ['image' => self::PHOTO],
        );

        $this->assertStringContainsString('is-image-end', $html);
        $this->assertStringContainsString('split-band--stone', $html);
        $this->assertStringContainsString('edge-panel', $html);
        $this->assertLessThan(strpos($html, 'image-stack'), strpos($html, 'section-copy'));

        $fallback = Blade::render(
            '<x-blocks.content.split heading="Fallback" body="Body." image-shape="oval" image-side="middle" background="purple" :image="$image" :cta="$cta" />',
            ['image' => self::PHOTO, 'cta' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)']],
        );

        $this->assertStringNotContainsString('is-image-circle', $fallback);
        $this->assertStringNotContainsString('is-image-end', $fallback);
        $this->assertStringNotContainsString('split-band', $fallback);
        $this->assertStringNotContainsString('javascript:', $fallback);
        $this->assertStringNotContainsString('split-action', $fallback);
    }

    public function test_split_without_an_image_is_a_statement_beside_the_detail_copy(): void
    {
        $html = Blade::render(
            '<x-blocks.content.split heading="Has disaster struck?" eyebrow="We have you covered." body="Call us any time." background="mist" :checklist="$points" :cta="$cta" />',
            ['points' => ['Upfront options'], 'cta' => ['label' => 'Call us now', 'href' => '#call']],
        );

        $this->assertStringContainsString('is-without-media', $html);
        $this->assertStringContainsString('split-band--mist', $html);
        $this->assertStringContainsString('class="split-statement"', $html);
        $this->assertStringContainsString('<p class="split-subheading">We have you covered.</p>', $html);
        $this->assertStringContainsString('Upfront options', $html);
        $this->assertLessThan(strpos($html, 'split-subheading'), strpos($html, 'split-action'));
        $this->assertStringNotContainsString('<img', $html);

        $statementOnly = Blade::render('<x-blocks.content.split heading="Just a statement" />');

        $this->assertStringContainsString('is-statement-only', $statementOnly);
        $this->assertStringNotContainsString('section-copy', $statementOnly);
    }

    public function test_quotes_band_renders_context_and_an_optional_outline_link(): void
    {
        $html = Blade::render(
            '<x-blocks.testimonials.quotes heading="What customers say" :items="$items" :link="$link" />',
            [
                'items' => [
                    ['quote' => 'Fixed the same day.', 'author' => 'Jordan Example', 'location' => 'Sample neighborhood'],
                    ['quote' => 'Careful and clear.', 'author' => 'Taylor Example', 'role' => 'Homeowner'],
                ],
                'link' => ['label' => 'Read more testimonials', 'href' => '/reviews'],
            ],
        );

        $this->assertStringContainsString('testimonial-band', $html);
        $this->assertStringContainsString('edge-panel', $html);
        $this->assertStringContainsString('Sample neighborhood', $html);
        $this->assertStringContainsString('Homeowner', $html);
        $this->assertStringContainsString('class="button button-outline" href="/reviews">Read more testimonials</a>', $html);
        $this->assertStringNotContainsString('is-single', $html);

        $single = Blade::render(
            '<x-blocks.testimonials.quotes heading="One" :items="$items" :link="$link" />',
            [
                'items' => [['quote' => 'Great.', 'author' => 'Casey Example']],
                'link' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ],
        );

        $this->assertStringContainsString('is-single', $single);
        $this->assertStringNotContainsString('testimonial-more', $single);
        $this->assertStringNotContainsString('javascript:', $single);
    }

    public function test_service_area_band_keeps_one_heading_and_an_optional_note(): void
    {
        $areas = [['name' => 'Springfield', 'href' => '/areas/springfield'], ['name' => 'Riverton']];

        $html = Blade::render(
            '<x-blocks.service-areas.list eyebrow="Plumbing services for these areas" heading="Where we work" note="And more…" :areas="$areas" />',
            ['areas' => $areas],
        );

        $this->assertStringContainsString('class="areas-band"', $html);
        $this->assertStringContainsString('<p class="eyebrow">Plumbing services for these areas</p>', $html);
        $this->assertStringContainsString('<h2 id="service-areas-title" class="sr-only">Where we work</h2>', $html);
        $this->assertStringContainsString('<p class="areas-note">And more…</p>', $html);
        $this->assertStringContainsString('href="/areas/springfield"', $html);
        $this->assertSame(1, substr_count($html, '<h2'));

        $labelled = Blade::render('<x-blocks.service-areas.list heading="Where we work" :areas="$areas" />', ['areas' => $areas]);

        $this->assertStringContainsString('<h2 id="service-areas-title" class="areas-label">Where we work</h2>', $labelled);
        $this->assertStringNotContainsString('areas-note', $labelled);
    }

    public function test_inline_band_underlines_one_phrase_and_switches_band_and_button(): void
    {
        $action = ['primaryAction' => ['label' => 'Reach out today', 'href' => '#contact']];

        $html = Blade::render(
            '<x-blocks.cta.inline-band heading="Let us help so you can get back to bed." emphasis="back to bed." :primary-action="$primaryAction" />',
            $action,
        );

        $this->assertStringContainsString('<strong class="heading-emphasis">back to bed.</strong>', $html);
        $this->assertStringContainsString('class="button button-orange" href="#contact"', $html);
        $this->assertStringNotContainsString('is-mist', $html);

        $mist = Blade::render(
            '<x-blocks.cta.inline-band heading="Not sure what you need?" background="mist" button-style="sky" :primary-action="$primaryAction" />',
            $action,
        );

        $this->assertStringContainsString('is-mist', $mist);
        $this->assertStringContainsString('class="button button-sky" href="#contact"', $mist);
    }

    public function test_cta_panel_offers_a_copper_guarantee_tone(): void
    {
        $cta = ['cta' => ['label' => 'Get in touch', 'href' => '#contact']];

        $copper = Blade::render(
            '<x-blocks.cta.panel eyebrow="Done right" heading="Guaranteed work" body="A short promise." tone="copper" :cta="$cta" />',
            $cta,
        );

        $this->assertStringContainsString('is-copper', $copper);
        $this->assertStringContainsString('class="button button-teal" href="#contact"', $copper);
        $this->assertStringContainsString('<p class="cta-panel-body">A short promise.</p>', $copper);

        $primary = Blade::render('<x-blocks.cta.panel heading="Plumbing emergencies" :cta="$cta" :image="$image" />', [
            ...$cta,
            'image' => self::PHOTO,
        ]);

        $this->assertStringNotContainsString('is-copper', $primary);
        $this->assertStringContainsString('class="button button-orange" href="#contact"', $primary);
        $this->assertStringContainsString('class="cta-panel-backdrop"', $primary);
        $this->assertStringContainsString('alt=""', $primary);
        $this->assertStringNotContainsString('cta-panel-body', $primary);
    }

    public function test_service_grid_shows_line_icons_with_two_line_titles(): void
    {
        $html = Blade::render(
            '<x-blocks.service-cards.grid heading="What can we help you with?" :services="$services" />',
            ['services' => [
                ['title' => 'Drain Cleaning + Repairs', 'body' => '', 'href' => '/services/drains', 'icon' => 'images/brand/icons/drain-cleaning.png'],
                ['title' => 'Gas Lines', 'body' => '', 'href' => '/services/gas', 'icon' => '/images/brand/icons/gas-lines.png'],
                ['title' => 'New Construction Work Done Well', 'body' => '', 'href' => '/services/new'],
            ]],
        );

        $this->assertStringContainsString('is-icon-grid', $html);
        $this->assertSame(3, substr_count($html, 'class="service-icon-card"'));
        $this->assertStringContainsString('images/brand/icons/drain-cleaning.png', $html);
        $this->assertStringContainsString('src="/images/brand/icons/gas-lines.png"', $html);
        $this->assertStringContainsString('<span>Drain Cleaning</span>', $html);
        $this->assertStringContainsString('<span>+ Repairs</span>', $html);
        $this->assertStringContainsString('<span>Gas</span>', $html);
        $this->assertStringContainsString('<span>Lines</span>', $html);
        $this->assertStringContainsString('<span>New Construction Work Done Well</span>', $html);
        $this->assertStringNotContainsString('glass-card', $html);

        $photos = Blade::render(
            '<x-blocks.service-cards.grid heading="Services" :services="$services" />',
            ['services' => [['title' => 'Repairs', 'body' => 'Fixes.', 'href' => '/services/repairs']]],
        );

        $this->assertStringNotContainsString('is-icon-grid', $photos);
        $this->assertStringContainsString('class="service-card"', $photos);
    }

    public function test_pricing_factors_band_takes_an_intro_photo_and_diamond_marks(): void
    {
        $html = Blade::render(
            '<x-blocks.pricing.factors heading="What to expect" body="Upfront, before any work." :image="$image" :factors="$factors" />',
            [
                'image' => self::PHOTO,
                'factors' => [
                    ['label' => 'Visit', 'description' => 'Explained first.'],
                    ['label' => 'Diagnosis', 'description' => 'Cause before cure.'],
                    ['label' => 'Options', 'description' => 'You choose.'],
                ],
            ],
        );

        $this->assertStringContainsString('Upfront, before any work.', $html);
        $this->assertStringContainsString('class="pricing-factors-backdrop"', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('is-count-3', $html);
        $this->assertSame(3, substr_count($html, 'class="pricing-factor-mark"'));
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d/', $html);
    }

    public function test_team_cards_show_an_optional_bio_under_the_role(): void
    {
        $image = ['src' => '/images/home/technician-controls.jpg', 'alt' => 'Fictional technician', 'width' => 1600, 'height' => 2844];

        $html = Blade::render('<x-blocks.team.grid heading="Meet the team" :members="$members" />', ['members' => [
            ['name' => 'Jordan Example', 'role' => 'Sample role', 'bio' => 'Fictional bio.', 'image' => $image],
            ['name' => 'Taylor Example', 'role' => 'Sample role', 'image' => $image],
        ]]);

        $this->assertSame(2, substr_count($html, 'class="team-card"'));
        $this->assertSame(2, substr_count($html, 'class="team-card-role"'));
        $this->assertSame(1, substr_count($html, 'class="team-card-bio"'));
        $this->assertStringContainsString('Fictional bio.', $html);
    }

    public function test_quote_form_errors_are_tied_to_their_fields(): void
    {
        Livewire::test(QuoteForm::class, ['preview' => true, 'formId' => 'contact-form'])
            ->call('submit')
            ->assertHasErrors('name')
            ->assertSeeHtml('aria-describedby="contact-form-name-error"')
            ->assertSeeHtml('id="contact-form-name-error"')
            ->assertSeeHtml('aria-invalid="true"');
    }

    public function test_registry_accepts_the_new_optional_props(): void
    {
        $registry = app(BlockRegistry::class);
        $link = ['label' => 'Go', 'href' => '#go'];

        foreach ([
            'hero/interior' => ['heading' => 'H', 'image' => self::PHOTO],
            'content/split' => [
                'heading' => 'Finally, a master plumber.', 'emphasis' => 'master plumber', 'body' => 'B',
                'lead' => 'L', 'cta' => $link, 'image' => self::PHOTO, 'imageShape' => 'circle',
                'imageSide' => 'end', 'background' => 'stone',
            ],
            'testimonials/quotes' => [
                'heading' => 'H', 'link' => $link,
                'items' => [['quote' => 'Q', 'author' => 'A', 'location' => 'Sample neighborhood']],
            ],
            'cta/inline-band' => [
                'heading' => 'Back to bed.', 'emphasis' => 'bed', 'primaryAction' => $link,
                'background' => 'mist', 'buttonStyle' => 'sky',
            ],
            'cta/panel' => ['heading' => 'H', 'body' => 'B', 'cta' => $link, 'tone' => 'copper'],
            'pricing/factors' => [
                'heading' => 'H', 'body' => 'B', 'image' => self::PHOTO,
                'factors' => [['label' => 'L', 'description' => 'D']],
            ],
            'team/grid' => ['heading' => 'H', 'members' => [[
                'name' => 'N', 'role' => 'R', 'bio' => 'B',
                'image' => ['src' => '/images/home/technician-controls.jpg', 'alt' => 'A', 'width' => 1600, 'height' => 2844],
            ]]],
        ] as $type => $props) {
            $this->assertSame([], $registry->validate($type, $props)->errors(), $type);
        }

        $this->assertSame(
            ['rectangle', 'circle'],
            $registry->definition('content/split')->fields['imageShape']['allowed'],
        );
        $this->assertSame(['white', 'mist', 'stone'], $registry->definition('content/split')->fields['background']['allowed']);
        $this->assertSame(['copper', 'sky'], $registry->definition('cta/inline-band')->fields['buttonStyle']['allowed']);
        $this->assertSame(['primary', 'copper'], $registry->definition('cta/panel')->fields['tone']['allowed']);
        $this->assertSame('string', $registry->definition('service-areas/list')->fields['note']['type']);

        foreach ([
            'content/split' => ['emphasis', 'lead', 'cta', 'imageShape', 'imageSide', 'background'],
            'hero/interior' => ['image'],
            'testimonials/quotes' => ['link'],
            'service-areas/list' => ['note'],
            'cta/inline-band' => ['emphasis', 'background', 'buttonStyle'],
            'cta/panel' => ['body', 'tone'],
            'pricing/factors' => ['body', 'image'],
        ] as $type => $fields) {
            foreach ($fields as $field) {
                $this->assertFalse($registry->definition($type)->fields[$field]['required'], "{$type}.{$field}");
            }
        }
    }

    public function test_registry_rejects_invalid_values_for_the_new_props(): void
    {
        $registry = app(BlockRegistry::class);
        $unsafe = ['label' => 'Bad', 'href' => 'javascript:alert(1)'];

        $split = $registry->validate('content/split', [
            'heading' => 'Heading', 'body' => 'Body', 'emphasis' => 'missing phrase',
            'imageShape' => 'oval', 'imageSide' => 'middle', 'background' => 'sky', 'cta' => $unsafe,
        ])->errors();

        foreach (['/props/emphasis', '/props/imageShape', '/props/imageSide', '/props/background', '/props/cta'] as $pointer) {
            $this->assertArrayHasKey($pointer, $split, $pointer);
        }

        $band = $registry->validate('cta/inline-band', [
            'heading' => 'H', 'primaryAction' => ['label' => 'Go', 'href' => '#go'],
            'background' => 'stone', 'buttonStyle' => 'teal', 'emphasis' => 'nope',
        ])->errors();

        foreach (['/props/background', '/props/buttonStyle', '/props/emphasis'] as $pointer) {
            $this->assertArrayHasKey($pointer, $band, $pointer);
        }

        $this->assertArrayHasKey('/props/link', $registry->validate('testimonials/quotes', ['heading' => 'H', 'link' => $unsafe])->errors());
        $this->assertArrayHasKey('/props/tone', $registry->validate('cta/panel', ['heading' => 'H', 'cta' => ['label' => 'Go', 'href' => '#go'], 'tone' => 'teal'])->errors());
        $this->assertArrayHasKey('/props/image', $registry->validate('hero/interior', ['heading' => 'H', 'image' => [...self::PHOTO, 'alt' => '']])->errors());
        $this->assertArrayHasKey('/props/image', $registry->validate('pricing/factors', [
            'heading' => 'H', 'factors' => [['label' => 'L', 'description' => 'D']], 'image' => [...self::PHOTO, 'width' => 0],
        ])->errors());
    }

    public function test_section_styles_use_tokens_only(): void
    {
        $css = (string) file_get_contents(resource_path('css/brand/sections.css'));

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $css);
        $this->assertDoesNotMatchRegularExpression('/:\s*(?:white|black)\s*(?:;|,|\))/i', $css);
        $this->assertDoesNotMatchRegularExpression('/@layer\b/', $css);

        preg_match_all('/border-radius:\s*([^;]+);/', $css, $radii);

        foreach ($radii[1] as $radius) {
            $this->assertMatchesRegularExpression('/^(?:0|var\(--radius-[a-z]+\))$/', trim($radius), "Literal radius: {$radius}");
        }

        foreach (['.interior-hero', '.split-band--mist', '.testimonial-band', '.areas-band', '.inline-cta.is-mist', '.cta-panel.is-copper', '.service-icon-grid', '.pricing-factor-mark', '.faq-item summary::after'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
    }
}
