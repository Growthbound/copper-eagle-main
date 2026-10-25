<?php

namespace Tests\Feature;

use App\Content\BlockRegistry;
use App\Content\ContentSchemaDocument;
use App\Content\Service;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

/**
 * hero/photo, service-cards/icon-strip, and content/feature-cards.
 *
 * Service records are fixtures in a throwaway content directory, so these
 * tests never see, or race, the site's own records in content/services.
 */
class CopperEagleBlocksTest extends TestCase
{
    use UsesFixtureContent;

    private const ICON = 'images/brand/icons/drain-cleaning.png';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
        $this->setUpFixtureContent();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    public function test_photo_hero_renders_the_header_then_the_strip_then_the_only_h1(): void
    {
        $this->fixtureService('fixture-drains', ['name' => 'Drain Cleaning + Repairs', 'icon' => self::ICON]);

        $html = $this->photoHero();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(0, substr_count($html, '<h2'));

        $header = strpos($html, '<header class="site-header');
        $strip = strpos($html, 'class="service-strip edge-panel"');
        $hero = strpos($html, 'class="photo-hero edge-panel"');

        $this->assertNotFalse($header);
        $this->assertNotFalse($strip);
        $this->assertNotFalse($hero);
        $this->assertTrue($header < $strip && $strip < $hero, 'The header, icon strip, and photo section render in that order.');

        $this->assertStringContainsString('id="hero" class="photo-hero edge-panel" aria-labelledby="hero-title"', $html);
        $this->assertStringContainsString('<h1 id="hero-title">', $html);
        $this->assertStringContainsString('<a class="button button-orange" href="#help">Contact us today</a>', $html);
        $this->assertStringContainsString('src="/images/brand/photos/drain.jpg"', $html);
        $this->assertStringContainsString('alt="Water running into a sink drain"', $html);
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="800"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('<p class="eyebrow">Plumbing in a sample city</p>', $html);
        $this->assertStringContainsString('/services/fixture-drains', $html);
    }

    public function test_photo_hero_emphasis_is_escaped_and_must_be_an_exact_phrase(): void
    {
        $html = $this->photoHero([
            'heading' => 'Fast & <b>careful</b> plumbing.',
            'emphasis' => '<b>careful</b>',
        ]);

        $this->assertStringContainsString(
            'Fast &amp; <strong class="heading-emphasis">&lt;b&gt;careful&lt;/b&gt;</strong> plumbing.',
            $html,
        );
        $this->assertStringNotContainsString('<b>careful</b>', $html);

        $plain = $this->photoHero(['heading' => 'A calm plumber.', 'emphasis' => 'not in the heading']);

        $this->assertMatchesRegularExpression('#<h1 id="hero-title">\s*A calm plumber\.\s*</h1>#', $plain);
        $this->assertStringNotContainsString('heading-emphasis', $plain);
    }

    public function test_photo_hero_leaves_out_the_strip_when_asked_or_when_no_service_has_an_icon(): void
    {
        $this->fixtureService('fixture-plain', ['name' => 'Plain Service']);

        $withoutIcons = $this->photoHero();
        $this->assertStringNotContainsString('service-strip', $withoutIcons);
        $this->assertSame(1, substr_count($withoutIcons, '<h1'));

        $this->fixtureService('fixture-drains', ['icon' => self::ICON]);

        $this->assertStringContainsString('service-strip', $this->photoHero());
        $this->assertStringNotContainsString('service-strip', $this->photoHero(['serviceStrip' => false]));
    }

    public function test_photo_hero_with_an_invalid_image_still_renders_its_copy_without_a_broken_img(): void
    {
        $html = $this->photoHero(['image' => ['src' => 'javascript:alert(1)', 'alt' => 'x', 'width' => 10, 'height' => 10]]);

        $this->assertStringContainsString('is-without-media', $html);
        $this->assertStringNotContainsString('photo-hero-image', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_icon_strip_reads_service_records_with_safe_icons_in_record_order(): void
    {
        $this->fixtureService('fixture-second', ['name' => 'Water Heaters + Repairs', 'icon' => self::ICON, 'order' => 2]);
        $this->fixtureService('fixture-first', ['name' => 'General Plumbing', 'icon' => '/'.self::ICON, 'order' => 1]);
        $this->fixtureService('fixture-no-icon', ['name' => 'No Icon', 'order' => 3]);
        $this->fixtureService('fixture-climbs', ['name' => 'Climbs Out', 'icon' => 'images/../../.env', 'order' => 4]);
        $this->fixtureService('fixture-remote', ['name' => 'Remote Icon', 'icon' => 'https://example.test/icon.png', 'order' => 5]);
        $this->fixtureService('fixture-missing', ['name' => 'Missing Icon', 'icon' => 'images/brand/icons/not-here.png', 'order' => 6]);
        $this->fixtureService('fixture-script', ['name' => 'Script Icon', 'icon' => 'javascript:alert(1)', 'order' => 7]);

        $html = Blade::render('<x-blocks.service-cards.icon-strip />');

        $this->assertStringContainsString('<nav id="service-strip" class="service-strip edge-panel" aria-label="Services">', $html);
        $this->assertSame(2, substr_count($html, 'class="service-strip-link"'));
        $this->assertLessThan(
            strpos($html, '/services/fixture-second'),
            strpos($html, '/services/fixture-first'),
        );
        $this->assertSame(2, substr_count($html, 'src="'.asset(self::ICON).'"'));
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('width="320"', $html);
        $this->assertStringContainsString('height="320"', $html);

        // Two-line captions: around a joining "+", otherwise between the words.
        $this->assertMatchesRegularExpression('#<span>Water Heaters</span>\s*<span>\+ Repairs</span>#', $html);
        $this->assertMatchesRegularExpression('#<span>General</span>\s*<span>Plumbing</span>#', $html);

        foreach (['No Icon', 'Climbs Out', 'Remote Icon', 'Missing Icon', 'Script Icon', '.env', 'example.test', 'javascript:'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }
    }

    public function test_icon_strip_honours_an_explicit_selection_and_escapes_names(): void
    {
        $this->fixtureService('fixture-a', ['name' => 'Alpha <script>x</script>', 'icon' => self::ICON, 'order' => 1]);
        $this->fixtureService('fixture-b', ['name' => 'Bravo', 'icon' => self::ICON, 'order' => 2]);
        $this->fixtureService('fixture-c', ['name' => 'Charlie', 'order' => 3]);

        $html = Blade::render(
            '<x-blocks.service-cards.icon-strip id="picked" :services="$services" />',
            ['services' => ['fixture-b', 'missing-service', 'fixture-c', 'fixture-a', 'fixture-b']],
        );

        $this->assertStringContainsString('id="picked"', $html);
        $this->assertSame(2, substr_count($html, 'class="service-strip-link"'));
        $this->assertLessThan(strpos($html, '/services/fixture-a'), strpos($html, '/services/fixture-b'));
        $this->assertStringNotContainsString('Charlie', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_icon_strip_renders_nothing_when_no_service_has_an_icon(): void
    {
        $this->assertSame('', trim(Blade::render('<x-blocks.service-cards.icon-strip />')));

        $this->fixtureService('fixture-plain', ['name' => 'Plain Service']);

        $this->assertSame('', trim(Blade::render('<x-blocks.service-cards.icon-strip />')));
    }

    #[DataProvider('iconProvider')]
    public function test_service_icon_is_only_a_safe_path_to_an_image_inside_public(mixed $icon, ?string $expected): void
    {
        $service = new Service('fixture', ['name' => 'Fixture', 'icon' => $icon]);

        $this->assertSame($expected, $service->icon());
    }

    public static function iconProvider(): iterable
    {
        yield 'relative path' => [self::ICON, self::ICON];
        yield 'leading slash' => ['/'.self::ICON, self::ICON];
        yield 'missing' => [null, null];
        yield 'not a string' => [['src' => self::ICON], null];
        yield 'missing file' => ['images/brand/icons/not-here.png', null];
        yield 'climbs out' => ['images/../../.env', null];
        yield 'remote' => ['https://example.test/icon.png', null];
        yield 'protocol relative' => ['//example.test/icon.png', null];
        yield 'script' => ['javascript:alert(1)', null];
        yield 'not an image' => ['index.php', null];
        yield 'directory' => ['images/brand/icons', null];
    }

    public function test_feature_cards_side_layout_renders_cards_watermark_and_escaped_accent_emphasis(): void
    {
        $html = Blade::render('<x-blocks.content.feature-cards :heading="$heading" :emphasis="$emphasis" :items="$items" />', [
            'heading' => 'The ultimate <i>peace</i> of mind.',
            'emphasis' => '<i>peace</i> of mind.',
            'items' => $this->cards(3),
        ]);

        $this->assertStringContainsString('id="features"', $html);
        $this->assertStringContainsString('feature-cards--side', $html);
        $this->assertStringContainsString('feature-cards--white', $html);
        $this->assertStringContainsString('has-watermark', $html);
        $this->assertStringContainsString('aria-labelledby="features-title"', $html);
        $this->assertStringContainsString(
            'The ultimate <strong class="heading-emphasis heading-emphasis--accent">&lt;i&gt;peace&lt;/i&gt; of mind.</strong>',
            $html,
        );
        $this->assertSame(0, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<h2'));
        $this->assertSame(3, substr_count($html, '<h3'));
        $this->assertSame(3, substr_count($html, 'class="feature-card-image"'));
        $this->assertStringContainsString('is-count-3', $html);
        $this->assertStringContainsString('width="900"', $html);
        $this->assertStringContainsString('height="900"', $html);
        $this->assertStringContainsString('alt="Card photo 1"', $html);
    }

    public function test_feature_cards_stacked_layout_on_stone_never_shows_the_watermark(): void
    {
        $html = Blade::render(
            '<x-blocks.content.feature-cards id="offerings" heading="Our drain services" layout="stacked" background="stone" :items="$items" />',
            ['items' => $this->cards(4)],
        );

        $this->assertStringContainsString('feature-cards--stacked', $html);
        $this->assertStringContainsString('feature-cards--stone', $html);
        $this->assertStringNotContainsString('has-watermark', $html);
        $this->assertStringContainsString('aria-labelledby="offerings-title"', $html);
        $this->assertStringContainsString('is-count-4', $html);

        $noWatermark = Blade::render(
            '<x-blocks.content.feature-cards heading="Values" :watermark="false" layout="unknown" background="purple" :items="$items" />',
            ['items' => $this->cards(2)],
        );

        $this->assertStringContainsString('feature-cards--side', $noWatermark);
        $this->assertStringContainsString('feature-cards--white', $noWatermark);
        $this->assertStringNotContainsString('has-watermark', $noWatermark);
    }

    public function test_feature_cards_leave_out_invalid_cards_and_render_nothing_below_two(): void
    {
        $items = $this->cards(3);
        $items[1]['image'] = ['src' => 'javascript:alert(1)', 'alt' => 'Bad', 'width' => 1, 'height' => 1];

        $html = Blade::render('<x-blocks.content.feature-cards heading="Values" :items="$items" />', ['items' => $items]);

        $this->assertSame(2, substr_count($html, '<h3'));
        $this->assertStringNotContainsString('javascript:', $html);

        $items[2]['title'] = '  ';

        $this->assertSame('', trim(Blade::render('<x-blocks.content.feature-cards heading="Values" :items="$items" />', ['items' => $items])));
    }

    public function test_feature_cards_render_up_to_six_cards(): void
    {
        $html = Blade::render(
            '<x-blocks.content.feature-cards id="offerings" heading="Our services" layout="stacked" :items="$items" />',
            ['items' => $this->cards(6)],
        );

        $this->assertSame(6, substr_count($html, '<h3'));
        $this->assertStringContainsString('is-count-6', $html);

        $five = Blade::render('<x-blocks.content.feature-cards heading="Values" :items="$items" />', ['items' => $this->cards(5)]);
        $this->assertSame(5, substr_count($five, '<h3'));
        $this->assertStringContainsString('is-count-5', $five);

        $seven = Blade::render('<x-blocks.content.feature-cards heading="Values" :items="$items" />', ['items' => $this->cards(7)]);
        $this->assertSame(6, substr_count($seven, '<h3'), 'A seventh card is never rendered.');
    }

    public function test_feature_card_bodies_split_on_blank_lines_into_escaped_paragraphs(): void
    {
        $items = $this->cards(2);
        $items[0]['body'] = "First <b>paragraph</b>.\nStill the first.\n\n  \n\nSecond paragraph.";

        $html = Blade::render('<x-blocks.content.feature-cards heading="Values" :items="$items" />', ['items' => $items]);

        $this->assertMatchesRegularExpression('#<p>First &lt;b&gt;paragraph&lt;/b&gt;\.\s+Still the first\.</p>\s*<p>Second paragraph\.</p>#', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('<p>A short fictional card body 2.</p>', $html);
        $this->assertSame(3, substr_count($html, '<p>'));
    }

    public function test_registry_accepts_six_feature_cards(): void
    {
        $result = app(BlockRegistry::class)->validate('content/feature-cards', [
            'heading' => 'Our services',
            'items' => [...$this->cards(5), ['body' => "One.\n\nTwo."] + $this->cards(6)[5]],
        ]);

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
    }

    public function test_registry_accepts_valid_payloads_for_all_three_blocks(): void
    {
        $this->fixtureService('fixture-drains', ['icon' => self::ICON]);
        $blocks = app(BlockRegistry::class);

        foreach ([
            'hero/photo' => $this->photoHeroProps(['emphasis' => 'too short', 'serviceStrip' => false]),
            'service-cards/icon-strip' => ['id' => 'service-strip', 'services' => ['fixture-drains']],
            'content/feature-cards' => [
                'heading' => 'The ultimate peace of mind.',
                'emphasis' => 'peace of mind.',
                'items' => $this->cards(4),
                'layout' => 'stacked',
                'background' => 'stone',
                'watermark' => false,
            ],
        ] as $type => $props) {
            $result = $blocks->validate($type, $props);

            $this->assertTrue($result->isValid(), $type.': '.json_encode($result->errors()));
        }
    }

    #[DataProvider('invalidPropsProvider')]
    public function test_registry_rejects_bad_props(string $type, array $props, string $pointer): void
    {
        $this->fixtureService('fixture-plain', ['name' => 'Plain Service']);

        $result = app(BlockRegistry::class)->validate($type, $props);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey($pointer, $result->errors(), json_encode($result->errors()));
    }

    public static function invalidPropsProvider(): iterable
    {
        $hero = self::basePhotoHeroProps();
        $cards = self::baseCards(3);

        yield 'hero without image' => ['hero/photo', array_diff_key($hero, ['image' => true]), '/props'];
        yield 'hero without action' => ['hero/photo', array_diff_key($hero, ['primaryAction' => true]), '/props/primaryAction'];
        yield 'hero unsafe action' => ['hero/photo', ['primaryAction' => ['label' => 'Go', 'href' => 'javascript:alert(1)']] + $hero, '/props/primaryAction'];
        yield 'hero image without dimensions' => ['hero/photo', ['image' => ['src' => '/images/brand/photos/drain.jpg', 'alt' => 'Drain']] + $hero, '/props/image'];
        yield 'hero emphasis not in heading' => ['hero/photo', ['emphasis' => 'something else'] + $hero, '/props/emphasis'];
        yield 'hero strip flag not boolean' => ['hero/photo', ['serviceStrip' => 'no'] + $hero, '/props/serviceStrip'];
        yield 'hero unknown prop' => ['hero/photo', ['cta' => ['label' => 'Go', 'href' => '#x']] + $hero, '/props/cta'];

        yield 'strip has no heading' => ['service-cards/icon-strip', ['heading' => 'Services'], '/props/heading'];
        yield 'strip unknown service' => ['service-cards/icon-strip', ['services' => ['missing-service']], '/props/services/0'];
        yield 'strip service without icon' => ['service-cards/icon-strip', ['services' => ['fixture-plain']], '/props/services/0'];
        yield 'strip service not a string' => ['service-cards/icon-strip', ['services' => [['slug' => 'x']]], '/props/services/0'];

        $cardsProps = ['heading' => 'Peace of mind.', 'items' => $cards];

        yield 'cards one item' => ['content/feature-cards', ['items' => self::baseCards(1)] + $cardsProps, '/props/items'];
        yield 'cards seven items' => ['content/feature-cards', ['items' => self::baseCards(7)] + $cardsProps, '/props/items'];
        yield 'cards missing items' => ['content/feature-cards', ['heading' => 'Peace of mind.'], '/props'];
        yield 'cards item without image' => ['content/feature-cards', ['items' => [array_diff_key($cards[0], ['image' => true]), $cards[1]]] + $cardsProps, '/props/items/0/image'];
        yield 'cards item with bad image' => ['content/feature-cards', ['items' => [['image' => ['src' => 'javascript:x', 'alt' => 'x', 'width' => 1, 'height' => 1]] + $cards[0], $cards[1]]] + $cardsProps, '/props/items/0/image'];
        yield 'cards item without body' => ['content/feature-cards', ['items' => [['body' => ''] + $cards[0], $cards[1]]] + $cardsProps, '/props/items/0'];
        yield 'cards bad layout' => ['content/feature-cards', ['layout' => 'grid'] + $cardsProps, '/props/layout'];
        yield 'cards bad background' => ['content/feature-cards', ['background' => 'mist'] + $cardsProps, '/props/background'];
        yield 'cards watermark not boolean' => ['content/feature-cards', ['watermark' => 'yes'] + $cardsProps, '/props/watermark'];
        yield 'cards emphasis not in heading' => ['content/feature-cards', ['emphasis' => 'calm'] + $cardsProps, '/props/emphasis'];
    }

    public function test_schema_exposes_each_contract_and_the_strip_anchor(): void
    {
        $schema = app(ContentSchemaDocument::class)->toArray()['blocks'];

        $hero = $schema['hero/photo'];
        $this->assertSame('blocks.hero.photo', $hero['component']);
        $this->assertSame(
            ['id', 'eyebrow', 'heading', 'emphasis', 'body', 'primaryAction', 'image', 'serviceStrip'],
            array_keys($hero['fields']),
        );
        $this->assertTrue($hero['fields']['heading']['required']);
        $this->assertTrue($hero['fields']['primaryAction']['required']);
        $this->assertTrue($hero['fields']['image']['required']);
        $this->assertSame('object', $hero['fields']['image']['shape']);
        $this->assertSame('boolean', $hero['fields']['serviceStrip']['type']);
        $this->assertFalse($hero['fields']['emphasis']['required']);

        $strip = $schema['service-cards/icon-strip'];
        $this->assertSame(['id', 'services'], array_keys($strip['fields']));
        $this->assertSame('list', $strip['fields']['services']['shape']);
        $this->assertSame('string', $strip['fields']['services']['items']['type']);

        $cards = $schema['content/feature-cards'];
        $this->assertSame(
            ['id', 'eyebrow', 'heading', 'emphasis', 'body', 'items', 'layout', 'background', 'watermark'],
            array_keys($cards['fields']),
        );
        $this->assertSame(['side', 'stacked'], $cards['fields']['layout']['allowed']);
        $this->assertSame(['white', 'stone'], $cards['fields']['background']['allowed']);
        $this->assertSame(2, $cards['fields']['items']['minItems']);
        $this->assertSame(6, $cards['fields']['items']['maxItems']);
        $this->assertSame(
            ['title', 'body', 'image'],
            array_keys($cards['fields']['items']['items']['properties']),
        );

        $blocks = app(BlockRegistry::class);
        $this->assertSame(['hero', 'hero-title', 'service-strip'], $blocks->emittedIds('hero/photo', ['heading' => 'Hi']));
        $this->assertSame(['hero', 'hero-title'], $blocks->emittedIds('hero/photo', ['heading' => 'Hi', 'serviceStrip' => false]));
        $this->assertSame(['features', 'features-title'], $blocks->emittedIds('content/feature-cards', ['heading' => 'Hi']));
        $this->assertSame(['service-strip'], $blocks->emittedIds('service-cards/icon-strip', []));

        $resolved = $blocks->renderProps('content/feature-cards', ['items' => $this->cards(2)]);
        $this->assertSame('/images/brand/photos/bath-sink.jpg', $resolved['items'][0]['image']['src']);
    }

    public function test_content_check_reports_a_service_icon_that_is_not_a_public_image(): void
    {
        $this->fixtureService('fixture-climbs', ['icon' => '../.env']);

        $this->artisan('content:check')
            ->expectsOutputToContain('services/fixture-climbs.json: the icon must be the path of an image file under public/')
            ->assertFailed();
    }

    public function test_content_check_accepts_a_valid_service_icon(): void
    {
        $this->fixtureService('fixture-drains', ['icon' => self::ICON]);

        $this->artisan('content:check')
            ->doesntExpectOutputToContain('the icon must be the path');
    }

    #[DataProvider('previewProvider')]
    public function test_gallery_previews_render_with_one_h1(string $slug, string $marker): void
    {
        $response = $this->get('/_templates/'.$slug)
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee($marker, false)
            ->assertSee('Fictional', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public static function previewProvider(): iterable
    {
        yield ['photo-hero', 'class="photo-hero edge-panel"'];
        yield ['service-icon-strip', 'class="service-strip edge-panel"'];
        yield ['feature-cards', 'feature-cards--stacked'];
    }

    public function test_photo_hero_preview_shows_the_sample_icon_strip(): void
    {
        $response = $this->get('/_templates/photo-hero')->assertOk();

        $this->assertSame(8, substr_count($response->getContent(), 'class="service-strip-link"'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function photoHero(array $overrides = []): string
    {
        // The page-template path: props arrive as one attribute bag.
        return Blade::render(
            '<x-dynamic-component component="blocks.hero.photo" :attributes="$bag" />',
            ['bag' => new ComponentAttributeBag($this->photoHeroProps($overrides))],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function photoHeroProps(array $overrides = []): array
    {
        return [...self::basePhotoHeroProps(), ...$overrides];
    }

    /**
     * @return array<string, mixed>
     */
    private static function basePhotoHeroProps(): array
    {
        return [
            'eyebrow' => 'Plumbing in a sample city',
            'heading' => 'A fictional plumber, too short to worry about.',
            'emphasis' => 'too short',
            'body' => 'A sample introduction.',
            'primaryAction' => ['label' => 'Contact us today', 'href' => '#help'],
            'image' => [
                'src' => '/images/brand/photos/drain.jpg',
                'alt' => 'Water running into a sink drain',
                'width' => 1200,
                'height' => 800,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cards(int $count): array
    {
        return self::baseCards($count);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function baseCards(int $count): array
    {
        return array_map(fn (int $index): array => [
            'title' => "Card title {$index}",
            'body' => "A short fictional card body {$index}.",
            'image' => [
                'src' => '/images/brand/photos/bath-sink.jpg',
                'alt' => "Card photo {$index}",
                'width' => 900,
                'height' => 900,
            ],
        ], range(1, $count));
    }
}
