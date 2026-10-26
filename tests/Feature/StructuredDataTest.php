<?php

namespace Tests\Feature;

use App\Content\AreaRepository;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Models\User;
use App\Support\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\UsesFixtureContent;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use IsolatesManagedContent;
    use ManagedContentFixtures;
    use RefreshDatabase;
    use UsesFixtureContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixtureContent();
        $this->fixtureHomePage();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    public function test_every_page_declares_the_business(): void
    {
        config()->set('site.business_type', null);

        $business = $this->businessNodeFrom('/');

        // A client that has not picked a type is still a typed entity.
        $this->assertSame('LocalBusiness', $business['@type']);
        $this->assertSame(config('site.name'), $business['name']);
        $this->assertSame(StructuredData::businessId(), $business['@id']);
    }

    public function test_the_markup_is_valid_json(): void
    {
        foreach (['/', '/services', $this->fixtureService(), $this->fixtureArea()] as $path) {
            $graph = $this->graphFrom($path);

            $this->assertSame('https://schema.org', $graph['@context']);
            $this->assertNotEmpty($graph['@graph']);
        }
    }

    public function test_public_pages_have_one_page_identity_matching_their_canonical(): void
    {
        $paths = [
            '/',
            $this->fixtureService(),
            $this->fixtureArea(),
            $this->publishManagedService('fixture-managed-service', 'Fixture Managed Service'),
            '/blog/',
        ];

        foreach ($paths as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $canonical);
            $pages = array_values(array_filter(
                $this->graphFrom($path)['@graph'],
                fn (array $node): bool => in_array('WebPage', (array) ($node['@type'] ?? []), true),
            ));

            $this->assertCount(1, $pages, $path);
            $this->assertSame($canonical[1], $pages[0]['url'], $path);
            $this->assertSame($canonical[1].'#webpage', $pages[0]['@id'], $path);
            $this->assertNotEmpty($pages[0]['name'], $path);
        }
    }

    public function test_fallback_page_identity_uses_metadata_without_inventing_an_article(): void
    {
        $nodes = StructuredData::withWebPage([], 'https://example.com/service/', 'Service title', 'Service description');

        $this->assertSame([[
            '@type' => 'WebPage',
            '@id' => 'https://example.com/service/#webpage',
            'name' => 'Service title',
            'description' => 'Service description',
            'url' => 'https://example.com/service/',
            'isPartOf' => ['@id' => StructuredData::websiteId()],
            'about' => ['@id' => StructuredData::businessId()],
        ]], $nodes);
    }

    public function test_the_site_is_declared_as_a_website_the_business_publishes(): void
    {
        $graph = collect($this->graphFrom('/')['@graph']);
        $website = $graph->firstWhere('@type', 'WebSite');
        $page = $graph->first(fn (array $node): bool => in_array('WebPage', (array) ($node['@type'] ?? []), true));

        $this->assertSame(StructuredData::websiteId(), $website['@id']);
        $this->assertSame(config('site.name'), $website['name']);
        $this->assertSame(StructuredData::businessId(), $website['publisher']['@id']);
        $this->assertSame(StructuredData::websiteId(), $page['isPartOf']['@id']);
    }

    /**
     * Service-area lists mix towns and counties. A county marked up as a City
     * points search engines at a town that does not exist.
     */
    public function test_a_county_is_declared_as_an_administrative_area(): void
    {
        $this->fixtureArea('fixture-county', ['name' => 'Fixture County', 'region' => 'Example State']);
        $this->fixtureArea('fixture-city', ['name' => 'Fixture City', 'region' => 'Example State']);

        $served = collect($this->businessNodeFrom('/')['areaServed'])->keyBy('name');

        $this->assertSame('AdministrativeArea', $served['Fixture County']['@type']);
        $this->assertSame('City', $served['Fixture City']['@type']);
    }

    public function test_opening_hours_and_people_come_only_from_site_settings(): void
    {
        config()->set('site.opening_hours', []);
        config()->set('site.people', []);

        $business = $this->businessNodeFrom('/');

        $this->assertArrayNotHasKey('openingHoursSpecification', $business);
        $this->assertArrayNotHasKey('employee', $business);

        config()->set('site.opening_hours', [
            ['days' => ['Monday', 'Sunday'], 'opens' => '00:00', 'closes' => '23:59'],
            ['days' => [], 'opens' => '08:00', 'closes' => '17:00'],
        ]);
        config()->set('site.people', [
            ['name' => 'Pat Example', 'job_title' => 'Owner', 'url' => '/about-pat'],
            ['name' => ' '],
        ]);

        $business = $this->businessNodeFrom('/');

        $this->assertSame([[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Sunday'],
            'opens' => '00:00',
            'closes' => '23:59',
        ]], $business['openingHoursSpecification']);
        $this->assertSame([[
            '@type' => 'Person',
            'name' => 'Pat Example',
            'jobTitle' => 'Owner',
            'url' => url('/about-pat'),
        ]], $business['employee']);
    }

    public function test_a_service_detail_page_describes_its_service_and_marks_up_only_real_questions(): void
    {
        $payload = $this->fixedPayload(PageTemplate::ServiceDetail, 'Fixture Drain Cleaning');
        $payload['sections']['faq']['items'] = [
            ['question' => 'Expertise', 'answer' => 'A labelled reason, not a question.'],
            ['question' => 'How often should drains be cleaned?', 'answer' => 'At least once a year.'],
        ];

        $path = $this->publishManagedPage(ContentKind::Page, PageTemplate::ServiceDetail, 'fixture-drain-cleaning', 'Fixture Drain Cleaning', $payload);

        $service = $this->nodeOfType($path, 'Service');
        $faq = $this->nodeOfType($path, 'FAQPage');

        $this->assertSame('Fixture Drain Cleaning', $service['name']);
        $this->assertSame(StructuredData::businessId(), $service['provider']['@id']);
        $this->assertSame(['How often should drains be cleaned?'], array_column($faq['mainEntity'], 'name'));
    }

    public function test_an_accordion_of_labels_is_not_marked_up_as_questions(): void
    {
        $payload = $this->fixedPayload(PageTemplate::ServiceDetail, 'Fixture Disposals');
        $payload['sections']['faq']['items'] = [
            ['question' => 'Expertise', 'answer' => 'A labelled reason.'],
            ['question' => 'Reliability', 'answer' => 'Another labelled reason.'],
        ];

        $path = $this->publishManagedPage(ContentKind::Page, PageTemplate::ServiceDetail, 'fixture-disposals', 'Fixture Disposals', $payload);

        $types = array_column($this->graphFrom($path)['@graph'], '@type');

        $this->assertNotContains('FAQPage', $types);
    }

    public function test_a_city_page_declares_plumbing_service_in_its_place(): void
    {
        $this->fixtureArea('fixture-springfield', ['name' => 'Springfield', 'region' => 'Example State']);
        $area = app(AreaRepository::class)->find('fixture-springfield');

        $payload = $this->fixedPayload(PageTemplate::City, 'Plumber in Springfield');
        $payload['sections']['hero']['place'] = 'Springfield';
        $payload['sections']['nearby-areas']['areas'] = [['name' => 'Springfield', 'href' => $area->url()]];

        $path = $this->publishManagedPage(ContentKind::Location, PageTemplate::City, 'plumber-springfield', 'Plumber in Springfield', $payload);

        $service = $this->nodeOfType($path, 'Service');

        $this->assertSame('Plumbing in Springfield, Example State', $service['name']);
        $this->assertSame('Plumbing', $service['serviceType']);
        $this->assertSame(StructuredData::businessId(), $service['provider']['@id']);
        $this->assertSame([[
            '@type' => 'City',
            'name' => 'Springfield',
            'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => 'Example State'],
        ]], $service['areaServed']);
    }

    public function test_an_article_names_the_business_as_its_publisher(): void
    {
        $path = $this->publishManagedPage(
            ContentKind::Article,
            PageTemplate::DecisionGuide,
            'blog/fixture-article',
            'Fixture Article',
            $this->fixedPayload(PageTemplate::DecisionGuide, 'Fixture Article'),
        );

        $article = $this->nodeOfType($path, 'Article');

        $this->assertSame(StructuredData::businessId(), $article['publisher']['@id']);
        // The fixture's share image becomes the article's image, as an absolute URL.
        $this->assertStringStartsWith('http://localhost/', $article['image']);
    }

    public function test_existing_page_identity_is_preserved_including_multiple_types(): void
    {
        $nodes = [['@type' => ['WebPage', 'CollectionPage'], '@id' => 'https://example.com/archive/#webpage']];

        $this->assertSame($nodes, StructuredData::withWebPage($nodes, 'https://example.com/', 'Fallback', null));
    }

    public function test_a_layout_without_a_canonical_does_not_invent_a_public_page_identity(): void
    {
        $html = (string) $this->blade('<x-layouts.marketing :canonical="false" :noindex="true">Preview</x-layouts.marketing>');

        $this->assertStringNotContainsString('"@type":"WebPage"', $html);
        $this->assertStringNotContainsString('#webpage', $html);
    }

    /**
     * Served areas come from the area records, so publishing an area is the
     * single act of adding its file.
     */
    public function test_the_business_lists_the_areas_it_serves(): void
    {
        $this->fixtureArea('fixture-springfield', ['name' => 'Springfield', 'region' => 'Example State']);
        $this->fixtureArea('fixture-unregioned', ['name' => 'Fixture Town', 'region' => '']);

        $served = collect($this->businessNodeFrom('/')['areaServed'])->keyBy('name');

        $this->assertSame('City', $served['Springfield']['@type']);
        $this->assertSame(
            ['@type' => 'AdministrativeArea', 'name' => 'Example State'],
            $served['Springfield']['containedInPlace'],
        );
        $this->assertArrayNotHasKey('containedInPlace', $served['Fixture Town']);
    }

    public function test_a_business_without_area_records_claims_no_service_area(): void
    {
        $this->assertArrayNotHasKey('areaServed', $this->businessNodeFrom('/'));
    }

    public function test_the_business_lists_its_services(): void
    {
        $this->fixtureService();

        $catalog = $this->businessNodeFrom('/')['hasOfferCatalog']['itemListElement'];

        $names = array_map(fn (array $offer): string => $offer['itemOffered']['name'], $catalog);

        $this->assertContains('Fixture Service', $names);
    }

    public function test_a_service_page_describes_that_service(): void
    {
        $this->fixtureArea();

        $service = $this->nodeOfType($this->fixtureService(), 'Service');

        $this->assertSame('Fixture Service', $service['name']);
        $this->assertSame(StructuredData::businessId(), $service['provider']['@id']);
        $this->assertNotEmpty($service['areaServed']);
    }

    public function test_an_uncommissioned_service_area_page_is_not_published(): void
    {
        $this->fixtureArea();

        $this->get($this->fixtureService().'/fixture-area')->assertNotFound();
    }

    public function test_nested_pages_carry_breadcrumbs_in_order(): void
    {
        $crumbs = $this->nodeOfType($this->fixtureService(), 'BreadcrumbList');

        $this->assertSame(
            ['Services', 'Fixture Service'],
            array_column($crumbs['itemListElement'], 'name'),
        );
        $this->assertSame([1, 2], array_column($crumbs['itemListElement'], 'position'));
    }

    public function test_contact_details_appear_once_the_owner_sets_them(): void
    {
        config()->set('site.phone', '');
        config()->set('site.address', '');

        $this->assertArrayNotHasKey('telephone', $this->businessNodeFrom('/'));

        config()->set('site.phone', '+1 555 555 0100');
        config()->set('site.address', '123 Example Street, Springfield');

        $business = $this->businessNodeFrom('/');

        $this->assertSame('+1 555 555 0100', $business['telephone']);
        $this->assertSame('123 Example Street, Springfield', $business['address']['streetAddress']);
    }

    /**
     * A graph that references a business at one host from pages on another is
     * not connected, so every URL in it has to share a host.
     */
    public function test_every_url_in_the_graph_shares_one_host(): void
    {
        $graph = $this->graphFrom($this->fixtureService());

        preg_match_all('#https?://[^"\\\\/]+#', json_encode($graph, JSON_UNESCAPED_SLASHES), $matches);

        // schema.org is the vocabulary, not one of our URLs, and the business's
        // other official profiles (sameAs) are meant to be on other hosts.
        $profiles = collect(config('site.social_links'))
            ->map(fn (array $link): string => (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $link['url']))
            ->all();
        $hosts = array_values(array_unique(array_diff($matches[0], ['https://schema.org'], $profiles)));

        $this->assertNotEmpty($hosts);
        $this->assertCount(1, $hosts, 'The graph mixes hosts: '.implode(', ', $hosts));
    }

    public function test_the_business_type_is_configurable_per_client(): void
    {
        config()->set('site.business_type', 'Plumber');

        $this->assertSame('Plumber', $this->businessNodeFrom('/')['@type']);
    }

    /**
     * Markup that disagrees with the page is worse than none, so the service
     * names in the graph must be the ones actually rendered.
     */
    public function test_the_markup_matches_what_the_page_shows(): void
    {
        $path = $this->fixtureService();
        $response = $this->get($path)->assertOk();

        $service = $this->nodeOfType($path, 'Service');

        $response->assertSee($service['name']);
    }

    private function publishManagedService(string $slug, string $title): string
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => ContentKind::Service,
            'slug' => $slug,
            'title' => $title,
            'template' => PageTemplate::DecisionGuide,
        ], $this->fixedPayload(PageTemplate::DecisionGuide, $title), $user);

        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);

        return '/'.$slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishManagedPage(ContentKind $kind, PageTemplate $template, string $slug, string $title, array $payload): string
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => $kind,
            'slug' => $slug,
            'title' => $title,
            'template' => $template,
        ], $payload, $user);

        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);

        return '/'.$slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function graphFrom(string $path): array
    {
        $content = $this->get($path)->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);

        $this->assertNotEmpty($matches, 'No JSON-LD found on '.$path);

        $decoded = json_decode(html_entity_decode($matches[1]), true);

        $this->assertIsArray($decoded, 'JSON-LD on '.$path.' did not parse');

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function businessNodeFrom(string $path): array
    {
        return $this->graphFrom($path)['@graph'][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeOfType(string $path, string $type): array
    {
        foreach ($this->graphFrom($path)['@graph'] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail('No '.$type.' node on '.$path);
    }
}
