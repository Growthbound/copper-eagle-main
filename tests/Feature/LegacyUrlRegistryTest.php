<?php

namespace Tests\Feature;

use App\Content\ContentValidator;
use App\Content\LegacyUrlRegistry;
use App\Content\MediaIndex;
use App\Content\RedirectValidator;
use App\Content\UrlDispositionRegistry;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use Tests\Concerns\BindsRegistryFixtures;
use Tests\TestCase;

class LegacyUrlRegistryTest extends TestCase
{
    use BindsRegistryFixtures;
    use RefreshDatabase;

    /**
     * Whatever registry this site ships — none is a valid answer — and the
     * fixture the platform tests run against.
     */
    public function test_the_registry_is_structurally_valid(): void
    {
        $errors = app(LegacyUrlRegistry::class)->validate();

        $this->assertSame([], $errors, implode("\n", $errors));

        $errors = $this->useLegacyUrlFixture()->validate();

        $this->assertSame([], $errors, 'The fixture registry: '.implode("\n", $errors));
    }

    /**
     * The families are site data, not platform behaviour. Another brand may
     * serve a different archive or own `/feed/` for something else, so an
     * install without a registry has to redirect nothing at all.
     */
    public function test_an_install_without_a_registry_redirects_nothing(): void
    {
        $registry = new LegacyUrlRegistry(
            app(MediaIndex::class),
            base_path('content/registries/this-brand-has-no-legacy-urls.json'),
        );

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->targetFor('/blog/page/2/'));
        $this->assertNull($registry->targetFor('/feed/'));
        $this->assertNull($registry->targetFor('/wp-content/uploads/Example-photo.png'));
    }

    /**
     * A registry that exists must be an object, and `[]` is the case that
     * slipped through.
     *
     * Associative decoding turns a JSON array into a PHP array, so an
     * `is_array()` guard accepts `[]` and it becomes an empty registry —
     * indistinguishable from the supported no-registry case. A file truncated
     * or overwritten to `[]` would then disable every legacy redirect in
     * production while structural validation reported no errors at all, which
     * is precisely the silence the guard was added to break.
     */
    public function test_a_registry_that_decodes_to_a_list_is_refused(): void
    {
        foreach (['[]', '[{"files":{}}]', 'null', '"a string"'] as $contents) {
            $path = base_path('storage/framework/testing/legacy-urls-malformed.json');
            file_put_contents($path, $contents);
            LegacyUrlRegistry::flush();

            try {
                (new LegacyUrlRegistry(app(MediaIndex::class), $path))->all();
                $this->fail("A registry containing {$contents} was accepted.");
            } catch (JsonException $e) {
                $this->assertStringContainsString('must contain a JSON object', $e->getMessage());
            } finally {
                @unlink($path);
                LegacyUrlRegistry::flush();
            }
        }
    }

    /**
     * A legacy target the ledger also forwards is a two-hop chain, and neither
     * authority can see it alone.
     *
     * The legacy middleware forwards the old path to the target, then the
     * ledger middleware forwards that target somewhere else. Each system's own
     * chain check passes, because each sees only its own rules. Search engines
     * discount the chain regardless of which of the two produced it.
     */
    public function test_a_legacy_target_the_ledger_forwards_is_reported_as_a_chain(): void
    {
        $this->useLedgerFixture();

        $forwarded = collect(app(UrlDispositionRegistry::class)->forwards())->keys()->first();

        $this->assertNotNull($forwarded, 'The ledger forwards nothing, so this test would pass vacuously.');

        $path = base_path('storage/framework/testing/legacy-urls-chained.json');
        file_put_contents($path, json_encode([
            'exact' => ['/an-old-path/' => $forwarded],
        ], JSON_THROW_ON_ERROR));
        LegacyUrlRegistry::flush();

        try {
            $errors = (new LegacyUrlRegistry(app(MediaIndex::class), $path))->validate();

            $this->assertContains(
                "{$forwarded} is a legacy destination and is also forwarded by the intent ledger, which is a chain.",
                $errors,
            );
        } finally {
            @unlink($path);
            LegacyUrlRegistry::flush();
        }
    }

    /**
     * A page cannot be published onto a path a legacy family already claims.
     *
     * `ApplyLegacyWordPressUrls` runs before the page fallback, so without this
     * the revision validates, previews correctly, and then 301s away from its
     * own public URL. The owner sees a successful publish and an unreachable
     * page, with nothing anywhere reporting a conflict.
     */
    public function test_a_page_cannot_claim_a_path_a_legacy_family_owns(): void
    {
        $this->useLegacyUrlFixture();

        foreach (['feed', 'author/new', 'blog/tag/new', 'blog/page/2', 'blog/category/news'] as $slug) {
            $this->assertTrue(
                app(ContentValidator::class)->validate(
                    ['kind' => 'page', 'slug' => $slug, 'title' => 'Taken', 'template' => 'standard'],
                    ['title' => 'Taken', 'blocks' => []],
                )->hasErrors(),
                "{$slug} was accepted as a page slug, but a legacy redirect owns it.",
            );
        }
    }

    public function test_an_ordinary_slug_under_the_same_prefixes_is_still_accepted(): void
    {
        $this->useLegacyUrlFixture();

        $this->assertFalse(
            app(ContentValidator::class)->validate(
                ['kind' => 'article', 'slug' => 'blog/a-real-article', 'title' => 'Fine', 'template' => 'standard'],
                ['title' => 'Fine', 'blocks' => []],
            )->hasErrors(),
        );
    }

    /**
     * The upload fields are validated too, because `uploadTarget()` skips
     * anything malformed in silence.
     *
     * Left unchecked, a mistyped prefix or a numeric file target produced a
     * registry that passed its structural test while the uploads it declares
     * answered 404 — a validator quiet about the shapes it never inspects
     * reads as coverage it does not have.
     */
    public function test_malformed_upload_declarations_are_reported(): void
    {
        $cases = [
            ['upload_prefixes' => '/wp-content/uploads/'],
            ['upload_prefixes' => ['wp-content/uploads/']],
            ['files' => ['a.png' => 42]],
            ['files' => ['a.png' => 'media/1']],
            ['files' => [['a.png', '/media/1']]],
        ];

        foreach ($cases as $index => $registry) {
            $path = base_path('storage/framework/testing/legacy-urls-uploads.json');
            file_put_contents($path, json_encode($registry, JSON_THROW_ON_ERROR));
            LegacyUrlRegistry::flush();

            try {
                $this->assertNotSame(
                    [],
                    (new LegacyUrlRegistry(app(MediaIndex::class), $path))->validate(),
                    'Case '.$index.' passed validation: '.json_encode($registry),
                );
            } finally {
                @unlink($path);
                LegacyUrlRegistry::flush();
            }
        }
    }

    /**
     * Every declared destination has to be somewhere this application actually
     * serves, or the registry is a list of redirects into 404s. Checked for the
     * registry this site ships, if any, and for the fixture the redirect tests
     * follow.
     */
    public function test_every_declared_destination_resolves(): void
    {
        $targets = [
            ...app(LegacyUrlRegistry::class)->declaredTargets(),
            ...$this->useLegacyUrlFixture()->declaredTargets(),
        ];

        $this->assertNotSame([], $targets);

        foreach (array_unique($targets) as $target) {
            $this->get($target)->assertOk();
        }
    }

    /**
     * Three systems serve redirects here, and each one's own chain check sees
     * only its own rules.
     */
    public function test_a_managed_rule_cannot_chain_into_a_legacy_family(): void
    {
        $this->useLegacyUrlFixture();

        $errors = app(RedirectValidator::class)->validate([
            'source_path' => '/legacy/archive',
            'target' => '/blog/page/2',
            'status_code' => 301,
            'enabled' => true,
        ])->errors();

        $this->assertArrayHasKey('/target', $errors);
        $this->assertStringContainsString('legacy URL', implode(' ', $errors['/target']));
    }

    public function test_a_managed_rule_to_an_ordinary_page_is_still_accepted(): void
    {
        $this->useLegacyUrlFixture();

        $user = User::factory()->create();

        $this->assertFalse(
            app(RedirectValidator::class)->validate([
                'source_path' => '/legacy/old-service',
                'target' => '/our-services/example-service',
                'status_code' => 301,
                'enabled' => true,
            ])->hasErrors(),
        );

        $this->assertInstanceOf(RedirectRule::class, RedirectRule::create([
            'source_path' => '/legacy/old-service',
            'target' => '/our-services/example-service',
            'status_code' => 301,
            'enabled' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));
    }
}
