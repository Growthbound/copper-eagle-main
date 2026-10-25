<?php

namespace Tests\Feature;

use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

class DiscoveryFilesTest extends TestCase
{
    use IsolatesManagedContent;
    use UsesFixtureContent;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://example.test');

        $this->setUpFixtureContent();
        $this->fixtureHomePage();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    public function test_robots_file_allows_crawling_and_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nAllow: /", false)
            ->assertSee("Disallow: /admin\nDisallow: /api\nDisallow: /_templates\nDisallow: /templates", false)
            ->assertSee('Sitemap: https://example.test/sitemap.xml', false);
    }

    /**
     * The summary states only what the owner has set. A detail left blank is
     * left out, rather than published as an empty field.
     */
    public function test_llms_file_contains_only_the_confirmed_site_identity(): void
    {
        config()->set('site.description', 'A fictional business used by the test suite.');
        config()->set('site.phone', '(555) 555-0100');
        config()->set('site.address', '123 Example Street, Springfield');
        config()->set('site.email', '');

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('# '.config('site.name'), false)
            ->assertSee('> A fictional business used by the test suite.', false)
            ->assertSee('Public homepage: https://example.test', false)
            ->assertSee('Phone: (555) 555-0100', false)
            ->assertSee('Address: 123 Example Street, Springfield', false)
            ->assertDontSee('Email:');
    }

    /**
     * An assistant answering "is this plumber open now?" or "who runs it?"
     * reads these lines, so they come straight from site settings.
     */
    public function test_llms_file_states_the_configured_hours_and_people(): void
    {
        config()->set('site.opening_hours', [
            ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 'opens' => '00:00', 'closes' => '23:59'],
        ]);
        config()->set('site.people', [['name' => 'Pat Example', 'job_title' => 'Owner']]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('Hours: Open 24 hours, 7 days a week', false)
            ->assertSee('People: Pat Example, Owner', false);

        config()->set('site.opening_hours', [
            ['days' => ['Monday', 'Friday'], 'opens' => '08:00', 'closes' => '17:00'],
        ]);

        $this->get('/llms.txt')->assertOk()->assertSee('Hours: Monday, Friday 08:00-17:00', false);
    }

    public function test_llms_file_for_a_new_install_states_nothing_it_was_not_told(): void
    {
        foreach (['description', 'phone', 'email', 'address'] as $field) {
            config()->set('site.'.$field, '');
        }
        config()->set('site.opening_hours', []);
        config()->set('site.people', []);

        $body = (string) $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertSame(
            '# '.config('site.name')."\n\nPublic homepage: https://example.test\n",
            $body,
        );
    }

    /**
     * The sitemap is built from the pages and records that exist, so adding a
     * service or an area publishes it without anyone editing a list.
     */
    public function test_sitemap_lists_every_page_the_site_actually_serves(): void
    {
        $this->fixturePage('fixture-about', [], ['title' => 'Fixture about page']);
        $this->fixturePage('fixture-held', [], ['title' => 'Fixture held page', 'noindex' => true]);
        $this->fixtureService();
        $this->fixtureArea();

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml');

        $this->assertStringStartsWith(
            '<?xml version="1.0" encoding="UTF-8"?>',
            $response->getContent(),
        );

        $response->assertSee('<loc>https://example.test</loc>', false);
        $response->assertSee('<loc>https://example.test/fixture-about</loc>', false);
        $response->assertSee('<loc>https://example.test/services</loc>', false);
        $response->assertSee('<loc>https://example.test/services/fixture-service</loc>', false);
        $response->assertSee('<loc>https://example.test/areas</loc>', false);
        $response->assertSee('<loc>https://example.test/areas/fixture-area</loc>', false);

        // A page that holds itself back is not submitted, and a service in an
        // area has no page of its own to submit.
        $response->assertDontSee('/fixture-held</loc>', false);
        $response->assertDontSee('/services/fixture-service/fixture-area</loc>', false);

        // Every URL listed has to actually resolve.
        preg_match_all('/<loc>([^<]+)<\/loc>/', $response->getContent(), $matches);
        $this->assertGreaterThan(1, count($matches[1]));

        foreach ($matches[1] as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';

            $this->get($path)->assertOk();
        }
    }

    public function test_discovery_files_are_stateless_and_publicly_cacheable(): void
    {
        foreach (['/robots.txt', '/llms.txt', '/sitemap.xml'] as $endpoint) {
            $this->get($endpoint)
                ->assertOk()
                ->assertHeaderMissing('set-cookie')
                ->assertHeader('cache-control', 'max-age=3600, public');
        }
    }

    /**
     * Service areas come from the area records, the same ones that generate
     * the area pages, so the summary cannot name a place the site has no page
     * for.
     */
    public function test_llms_file_includes_the_address_and_published_service_areas(): void
    {
        config()->set('site.address', '123 Example Street, Springfield');

        $this->get('/llms.txt')->assertOk()->assertDontSee('Service areas:');

        $this->fixtureArea('fixture-springfield', ['name' => 'Springfield', 'region' => 'Example State']);
        $this->fixtureArea('fixture-town', ['name' => 'Fixture Town', 'region' => '']);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('Address: 123 Example Street, Springfield', false)
            ->assertSee('Service areas: Fixture Town; Springfield, Example State', false);
    }
}
