<?php

namespace Tests\Feature;

use App\Content\AreaRepository;
use App\Content\ContentValidator;
use App\Content\UrlDispositionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The city pages as they ship.
 *
 * A city page is only worth having if it says something true about that
 * place, so each one has to cite its sources, name a real service area, and
 * be the page its generated /areas/ address folds into. Otherwise it is a
 * doorway page with a swapped city name.
 */
class CopperEagleCityPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        UrlDispositionRegistry::flush();
    }

    protected function tearDown(): void
    {
        UrlDispositionRegistry::flush();

        parent::tearDown();
    }

    public function test_every_city_page_is_valid_local_and_sourced(): void
    {
        $pages = $this->cityPages();

        $this->assertNotEmpty($pages, 'No city pages found.');

        foreach ($pages as $file => $document) {
            $identity = $document['identity'];
            $sections = $document['payload']['sections'];

            $this->assertSame('location', $identity['kind'], $file);
            $this->assertSame('city', $identity['template'], $file);
            $this->assertSame(
                [],
                app(ContentValidator::class)->validate($identity, $document['payload'])->errors(),
                $file.' does not validate.',
            );

            $place = $sections['hero']['place'];
            $this->assertNotNull(
                app(AreaRepository::class)->all()->first(fn ($area): bool => $area->name() === $place),
                "{$file}: {$place} is not a service area record.",
            );

            $sources = array_column($sections['sources']['items'], 'href');
            $this->assertGreaterThanOrEqual(3, count($sources), "{$file} cites fewer than three sources.");
            foreach ($sources as $href) {
                $this->assertStringStartsWith('https://', $href, "{$file} cites a source that is not https.");
            }

            $this->assertLessThanOrEqual(160, mb_strlen($document['payload']['description']), "{$file}: description too long.");
        }
    }

    public function test_each_city_page_renders_with_its_service_markup(): void
    {
        foreach ($this->cityPages() as $file => $document) {
            $path = '/'.$document['identity']['slug'];
            $place = $document['payload']['sections']['hero']['place'];

            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$path} must have exactly one H1.");
            $this->assertStringContainsString('"areaServed":[{"@type":', $html, $path);
            $this->assertStringContainsString('"name":"'.$place.'"', $html, $path);
        }
    }

    public function test_each_generated_area_page_folds_into_its_city_page(): void
    {
        $areas = app(AreaRepository::class);

        foreach ($this->cityPages() as $file => $document) {
            $place = $document['payload']['sections']['hero']['place'];
            $area = $areas->all()->first(fn ($candidate): bool => $candidate->name() === $place);

            $response = $this->get('/areas/'.$area->slug.'/')->assertStatus(301);

            $this->assertSame(
                '/'.$document['identity']['slug'].'/',
                parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
                "/areas/{$area->slug}/ should fold into its city page.",
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cityPages(): array
    {
        $pages = [];

        foreach (glob(base_path('content/managed/plumber-*.json')) ?: [] as $path) {
            $pages[basename($path)] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        }

        return $pages;
    }
}
