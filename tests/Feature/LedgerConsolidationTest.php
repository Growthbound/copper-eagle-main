<?php

namespace Tests\Feature;

use App\Content\UrlDispositionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BindsRegistryFixtures;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

/**
 * Proves the ledger's consolidation decisions are served, not just recorded.
 *
 * A ledger can carry a fold while the duplicate keeps answering 200 and keeps
 * appearing in the sitemap. The gap between a recorded decision and the site's
 * behaviour is the failure this file is here to make impossible to
 * reintroduce.
 *
 * Which URLs a site folds is its own data, so these run against the fixture
 * ledger in `tests/Fixtures/registries/`, with temporary service records for
 * the pages it talks about.
 */
class LedgerConsolidationTest extends TestCase
{
    use BindsRegistryFixtures;
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLedgerFixture();

        foreach (['pillar', 'duplicate', 'variant', 'held'] as $name) {
            $this->fixtureService('ledger-fixture-'.$name, ['name' => 'Ledger Fixture '.ucfirst($name)]);
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    /**
     * Only forwarding dispositions become folds, and both sides are normalized
     * so a source recorded without its trailing slash still matches.
     */
    public function test_the_registry_reads_the_folds_the_ledger_actually_records(): void
    {
        $forwards = app(UrlDispositionRegistry::class)->forwards();

        $this->assertSame([
            '/services/ledger-fixture-duplicate/' => '/services/ledger-fixture-pillar/',
            '/services/ledger-fixture-variant/' => '/services/ledger-fixture-pillar/',
        ], collect($forwards)->sortKeys()->all());
    }

    /**
     * One permanent hop, landing on a page that answers for itself rather than
     * on another redirect.
     */
    public function test_every_folded_url_permanently_redirects_to_its_recorded_target(): void
    {
        $forwards = app(UrlDispositionRegistry::class)->forwards();

        $this->assertNotSame([], $forwards);

        foreach ($forwards as $source => $target) {
            foreach ([$source, rtrim($source, '/')] as $requested) {
                $response = $this->get($requested);

                $this->assertSame(
                    301,
                    $response->getStatusCode(),
                    "{$requested} should permanently redirect to {$target}.",
                );

                $this->assertSame(
                    $target,
                    parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
                    "{$requested} redirects somewhere other than its disposition_target.",
                );
            }

            $this->get($target)->assertOk();
        }
    }

    public function test_a_folded_url_carries_its_campaign_parameters_across_the_hop(): void
    {
        $location = $this->get('/services/ledger-fixture-duplicate?utm_source=mail&utm_campaign=spring')
            ->assertStatus(301)
            ->headers->get('Location');

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertSame('/services/ledger-fixture-pillar/', parse_url((string) $location, PHP_URL_PATH));
        $this->assertSame([
            'utm_campaign' => 'spring',
            'utm_source' => 'mail',
        ], collect($query)->sortKeys()->all());
    }

    /**
     * A page held out of the index shares the prefix of the folded duplicates,
     * but being held for evidence is not the same decision as being folded
     * away: it still serves its own page.
     */
    public function test_a_page_held_for_evidence_is_not_swept_up_by_the_fold(): void
    {
        $this->get('/services/ledger-fixture-held')->assertOk();
    }

    public function test_the_sitemap_promotes_the_pillars_rather_than_the_folded_duplicates(): void
    {
        $body = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (app(UrlDispositionRegistry::class)->forwards() as $source => $target) {
            $this->assertStringNotContainsString(
                '<loc>'.url(rtrim($source, '/')).'</loc>',
                $body,
                "{$source} answers 301 and must not be submitted for crawling.",
            );

            $this->assertStringContainsString(
                '<loc>'.url(rtrim($target, '/')).'</loc>',
                $body,
                "{$target} absorbs {$source} and must be in the sitemap.",
            );
        }
    }
}
