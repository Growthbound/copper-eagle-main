<?php

namespace Tests\Feature;

use App\Content\BlockRegistry;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\PageTemplateRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The home page's "Local Reviews": the job check-ins the team posts, rebuilt
 * from the live site's widget as a governed block. Two show at first and
 * "Load more check-ins" reveals the rest, as on the live site.
 */
class LocalReviewsTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = [
        'src' => '/images/brand/photos/copper-pipe.jpg',
        'alt' => 'Copper pipe joint',
        'width' => 1200,
        'height' => 800,
    ];

    public function test_each_check_in_shows_who_did_the_work_when_what_they_did_and_the_photos(): void
    {
        $items = [
            ['author' => 'Jordan Walker', 'date' => '2026-09-01', 'body' => "Replaced a leaking water heater.\n\nHot water was back the same day.", 'photos' => [self::PHOTO, [...self::PHOTO, 'alt' => 'New water heater']]],
            ['author' => 'Matt Hibler', 'date' => '2026-04-08', 'body' => 'Cleared a slow tub drain.'],
            ['author' => 'Spencer Kaiserman', 'date' => '2026-02-04', 'body' => '', 'photos' => [self::PHOTO]],
        ];

        $html = Blade::render('<x-blocks.evidence.check-ins heading="Local Reviews" :items="$items" />', compact('items'));

        $this->assertSame(1, substr_count($html, '<h2'));
        $this->assertStringContainsString('>Local Reviews</h2>', $html);
        $this->assertSame(3, substr_count($html, 'data-check-in '));
        $this->assertStringContainsString('data-check-ins-batch="2"', $html);

        $this->assertStringContainsString('Jordan Walker', $html);
        $this->assertStringContainsString('<time datetime="2026-09-01">September 1, 2026</time>', $html);
        $this->assertStringContainsString('>JW</span>', $html);
        $this->assertStringContainsString('<p>Replaced a leaking water heater.</p>', $html);
        $this->assertStringContainsString('<p>Hot water was back the same day.</p>', $html);

        $this->assertMatchesRegularExpression('#<img[^>]*src="[^"]*/images/brand/photos/copper-pipe.jpg"[^>]*alt="Copper pipe joint"[^>]*width="1200"[^>]*height="800"[^>]*loading="lazy"#', $html);
        $this->assertStringContainsString('aria-label="Show photo 2 of 2"', $html);
        $this->assertSame(1, substr_count($html, 'data-check-in-photo-dots'), 'Only a check-in with several photos gets photo controls.');
        $this->assertStringContainsString('class="check-in is-text-only"', $html, 'A check-in without photos is text only.');

        $this->assertMatchesRegularExpression('#<button[^>]*data-check-ins-more-button[^>]*>\s*Load more check-ins\s*</button>#', $html);
    }

    public function test_a_check_in_needs_an_author_a_real_date_and_words_or_photos(): void
    {
        $registry = app(BlockRegistry::class);
        $checkIn = ['author' => 'Jordan Walker', 'date' => '2026-09-01', 'body' => 'Replaced a water heater.', 'photos' => [self::PHOTO]];
        $valid = ['heading' => 'Local Reviews', 'items' => [$checkIn]];

        $this->assertSame([], $registry->validate('evidence/check-ins', $valid)->errors());
        $this->assertSame([], $registry->validate('evidence/check-ins', [...$valid, 'items' => [[...$checkIn, 'body' => '']]])->errors(), 'Photos alone are enough.');
        $this->assertSame([], $registry->validate('evidence/check-ins', [...$valid, 'items' => [[...$checkIn, 'photos' => []]]])->errors(), 'Words alone are enough.');

        foreach ([
            'no author' => [...$checkIn, 'author' => ' '],
            'not a date' => [...$checkIn, 'date' => 'September 1'],
            'impossible date' => [...$checkIn, 'date' => '2026-13-01'],
            'nothing to show' => [...$checkIn, 'body' => '', 'photos' => []],
            'photo without alt' => [...$checkIn, 'photos' => [[...self::PHOTO, 'alt' => '']]],
            'too many photos' => [...$checkIn, 'photos' => array_fill(0, 9, self::PHOTO)],
        ] as $case => $item) {
            $this->assertArrayHasKey('/props/items/0', $registry->validate('evidence/check-ins', [...$valid, 'items' => [$item]])->errors(), $case);
        }

        $this->assertArrayHasKey('/props/items', $registry->validate('evidence/check-ins', [...$valid, 'items' => array_fill(0, 61, $checkIn)])->errors());
        $this->assertContains('The heading field is required.', $registry->validate('evidence/check-ins', ['items' => [$checkIn]])->errors()['/props'] ?? []);
    }

    public function test_the_home_page_shows_the_live_sites_local_reviews_after_the_help_prompt(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/data-page-section="([^"]+)"/', $html, $sections);
        $this->assertSame(['hero', 'introduction', 'testimonials', 'assurances', 'service-areas', 'help-prompt', 'local-reviews'], $sections[1]);
        $this->assertLessThan(strpos($html, 'class="team-spotlight'), strpos($html, 'data-page-section="local-reviews"'));

        $this->assertStringContainsString('>Local Reviews</h2>', $html);
        $this->assertSame(36, substr_count($html, 'data-check-in '));
        $this->assertStringContainsString('<time datetime="2026-09-01">September 1, 2026</time>', $html);
        $this->assertStringContainsString('the very same day', $html);
        $this->assertStringContainsString('Load more check-ins', $html);
    }

    public function test_every_check_in_photo_ships_at_its_declared_size_without_location_data(): void
    {
        $home = json_decode((string) file_get_contents(base_path('content/managed/home.json')), true, flags: JSON_THROW_ON_ERROR);
        $photos = collect($home['payload']['sections']['local-reviews']['items'])->pluck('photos')->filter()->flatten(1);

        $this->assertCount(78, $photos);

        foreach ($photos as $photo) {
            $path = public_path(ltrim($photo['src'], '/'));

            $this->assertStringStartsWith('/images/brand/check-ins/', $photo['src']);
            $this->assertFileExists($path);
            $this->assertSame([$photo['width'], $photo['height']], array_slice(getimagesize($path), 0, 2), "{$photo['src']} is not the size it declares.");
            $this->assertStringNotContainsString("Exif\x00\x00", (string) file_get_contents($path), "{$photo['src']} still carries camera metadata, which can hold the customer's location.");
            $this->assertNotSame('', trim($photo['alt']));
        }
    }

    public function test_a_home_page_published_before_local_reviews_existed_still_renders(): void
    {
        $definition = app(PageTemplateRegistry::class)->definition(PageTemplate::Home);
        $this->assertNotContains('local-reviews', $definition->requiredSections);
        $this->assertContains('local-reviews', $definition->sequence);

        $home = json_decode((string) file_get_contents(base_path('content/managed/home.json')), true, flags: JSON_THROW_ON_ERROR);
        $payload = $home['payload'];
        unset($payload['sections']['local-reviews']);

        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            ['kind' => ContentKind::Page, 'slug' => '/', 'title' => $home['identity']['title'], 'template' => PageTemplate::Home],
            $payload,
            $user,
        );
        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-page-section="help-prompt"', $html);
        $this->assertStringNotContainsString('data-page-section="local-reviews"', $html);
    }
}
