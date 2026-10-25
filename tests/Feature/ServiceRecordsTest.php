<?php

namespace Tests\Feature;

use App\Content\AreaRepository;
use App\Content\ServiceRepository;
use Illuminate\Support\Facades\File;
use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class ServiceRecordsTest extends TestCase
{
    use IsolatesManagedContent;
    use WritesRecordFixtures;

    /** @var array<int, string> */
    private array $temporary = [];

    /** @var array<int, string> */
    private array $createdDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            File::delete($path);
        }

        // Deepest first, and only when empty: a directory this test created
        // for a page file should not outlive it, but one that something else
        // has since put a file in is not ours to remove.
        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (File::isDirectory($directory) && File::isEmptyDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }

        $this->temporary = [];
        $this->createdDirectories = [];
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_a_service_record_becomes_a_page(): void
    {
        $this->get($this->fixtureService())
            ->assertOk()
            ->assertSee('Fixture Service')
            ->assertSee('<title>Fixture Service | '.e(config('site.name')).'</title>', false);
    }

    public function test_an_area_record_becomes_a_page(): void
    {
        $this->get($this->fixtureArea())
            ->assertOk()
            ->assertSee('Fixture Area')
            ->assertSee('Fixture Park');
    }

    public function test_service_area_combinations_are_not_published_automatically(): void
    {
        $service = $this->fixtureService();
        $this->fixtureArea();

        $this->get($service.'/fixture-area')->assertNotFound();
    }

    public function test_the_index_pages_list_every_record(): void
    {
        $this->fixtureService();
        $this->fixtureService('fixture-second-service', ['name' => 'Fixture Second Service']);
        $this->fixtureArea();
        $this->fixtureArea('fixture-second-area', ['name' => 'Fixture Second Area']);

        $this->get('/services')->assertOk()->assertSee('Fixture Service')->assertSee('Fixture Second Service');
        $this->get('/areas')->assertOk()
            ->assertSee('Fixture Area, Example State')
            ->assertSee('Fixture Second Area, Example State');
    }

    public function test_an_unknown_record_is_not_found(): void
    {
        $service = $this->fixtureService();

        $this->get('/services/we-do-not-offer-this')->assertNotFound();
        $this->get('/areas/nowhere')->assertNotFound();
        $this->get($service.'/nowhere')->assertNotFound();
    }

    public function test_a_service_limited_to_certain_areas_is_not_offered_elsewhere(): void
    {
        $this->fixtureArea();
        $this->fixtureArea('fixture-other-area', ['name' => 'Fixture Other Area']);
        $this->writeRecord('services', 'fixture-limited-service', [
            'name' => 'Fixture Limited Service',
            'summary' => 'Offered in one area.',
            'areas' => ['fixture-area'],
        ]);

        $this->get('/services/fixture-limited-service/fixture-area')->assertNotFound();
        $this->get('/services/fixture-limited-service/fixture-other-area')->assertNotFound();

        $this->writeRecord('pages/services/fixture-limited-service', 'fixture-area', [
            'title' => 'Commissioned limited service page',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Commissioned page']]],
        ]);

        $this->get('/services/fixture-limited-service/fixture-area')
            ->assertOk()
            ->assertSee('Commissioned page');
    }

    public function test_a_page_file_replaces_the_generated_page(): void
    {
        $path = $this->fixtureService();
        $this->writeRecord('pages/services', 'fixture-service', [
            'title' => 'Hand written service page',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Written by hand']]],
        ]);

        $this->get($path)
            ->assertOk()
            ->assertSee('Written by hand')
            // The generated page's own heading is gone; only the page file rendered.
            ->assertDontSee('About Fixture Service');
    }

    public function test_a_page_file_works_for_a_slug_with_no_record_behind_it(): void
    {
        $this->writeRecord('pages/services', 'fixture-emergency-callout', [
            'title' => 'Emergency callout',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Call us now']]],
        ]);

        $this->get('/services/fixture-emergency-callout')->assertOk()->assertSee('Call us now');
    }

    public function test_generated_pages_appear_in_the_sitemap(): void
    {
        $service = $this->fixtureService();
        $area = $this->fixtureArea();

        $sitemap = $this->get('/sitemap.xml')->assertOk();

        $sitemap->assertSee($service);
        $sitemap->assertSee($area);
        $sitemap->assertDontSee($service.'/fixture-area');
    }

    public function test_the_enquiry_form_offers_exactly_the_services_on_record(): void
    {
        $this->fixtureService();

        $names = app(ServiceRepository::class)->names();

        $this->assertContains('Fixture Service', $names);

        // The homepage carries the quote form, whose dropdown reads the records.
        $this->get('/')->assertOk()->assertSee('Fixture Service');
    }

    public function test_the_footer_links_to_each_area(): void
    {
        $this->fixtureArea();
        $this->fixtureArea('fixture-second-area', ['name' => 'Fixture Second Area']);

        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="Service areas"', false)
            ->assertSee('href="'.url('/areas/fixture-area').'"', false)
            ->assertSee('Fixture Area')
            ->assertSee('href="'.url('/areas/fixture-second-area').'"', false);
    }

    public function test_records_are_ordered_deliberately(): void
    {
        // Ordered against the alphabet, so only the order field can put the
        // "Zeta" record first; unordered records fall to the end by name.
        $this->fixtureService('fixture-alpha-service', ['name' => 'Alpha Fixture Service']);
        $this->fixtureService('fixture-zeta-service', ['name' => 'Zeta Fixture Service', 'order' => 1]);
        $this->fixtureArea('fixture-alpha-area', ['name' => 'Alpha Fixture Area']);
        $this->fixtureArea('fixture-zeta-area', ['name' => 'Zeta Fixture Area', 'order' => 1]);

        $services = app(ServiceRepository::class)->all()->map->name()->values()->all();
        $areas = app(AreaRepository::class)->all()->map->name()->values()->all();

        $this->assertLessThan(
            array_search('Alpha Fixture Service', $services, true),
            array_search('Zeta Fixture Service', $services, true),
        );
        $this->assertLessThan(
            array_search('Alpha Fixture Area', $areas, true),
            array_search('Zeta Fixture Area', $areas, true),
        );
    }

    public function test_an_area_without_local_detail_is_flagged_as_thin(): void
    {
        $this->fixtureArea();
        $this->writeRecord('areas', 'fixture-nondescript', ['name' => 'Nondescript']);

        $area = app(AreaRepository::class)->find('fixture-nondescript');

        $this->assertFalse($area->hasLocalContent());
        $this->assertTrue(app(AreaRepository::class)->find('fixture-area')->hasLocalContent());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeRecord(string $directory, string $slug, array $data): void
    {
        $path = base_path('content/'.$directory.'/'.$slug.'.json');

        $this->ensureDirectory(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->temporary[] = $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (File::isDirectory($directory)) {
            return;
        }

        $this->ensureDirectory(dirname($directory));

        File::makeDirectory($directory, 0755, false, true);
        $this->createdDirectories[] = $directory;
    }
}
