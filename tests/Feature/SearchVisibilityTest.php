<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A staging copy of a marketing site is a complete duplicate of the real one,
 * canonicalizing to itself. Left open it competes with production for its own
 * queries and gets ingested by model crawlers that keep repeating it long after
 * the host is retired.
 *
 * The host is the signal rather than `APP_ENV`, because staging is routinely
 * deployed with `APP_ENV=production`.
 */
class SearchVisibilityTest extends TestCase
{
    public function test_the_canonical_host_is_indexable_and_advertises_its_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nAllow: /", false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false)
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get('/')->assertOk()->assertDontSee('name="robots"', false);
    }

    public function test_every_page_on_a_non_canonical_host_is_noindexed(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        foreach (['/', '/services', '/areas', '/blog/'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    /**
     * Blocking a search engine outright does the opposite of what it promises:
     * a crawler that cannot fetch the page never sees the `noindex` on it, so
     * URLs it already holds sit in the index as "indexed, though blocked"
     * indefinitely. It has to be let in to be told to leave.
     */
    public function test_a_non_canonical_host_lets_search_crawlers_in_to_read_the_noindex(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nAllow: /", false);
    }

    /**
     * A training corpus has no retraction, so the only useful moment to refuse
     * is before the fetch.
     */
    public function test_a_non_canonical_host_refuses_answer_engine_crawlers(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        $body = (string) $this->get('/robots.txt')->assertOk()->getContent();

        foreach (config('search.answer_engine_crawlers') as $crawler) {
            $this->assertStringContainsString(
                "User-agent: {$crawler}\nDisallow: /",
                $body,
                "{$crawler} is not refused.",
            );
        }
    }

    /**
     * The meta tag only reaches HTML rendered through the layout. The sitemap
     * lists every URL the host has and the images sit behind them; none can
     * carry a meta tag, and all are indexable on their own.
     */
    public function test_the_noindex_travels_as_a_header_on_everything(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        foreach (['/', '/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_a_non_canonical_host_does_not_advertise_its_sitemap(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        $this->get('/robots.txt')->assertOk()->assertDontSee('Sitemap:', false);
    }

    /**
     * The failure mode this protects against is a forgotten variable on a host
     * nobody meant to publish, so an unset value cannot mean "publish it".
     * Production is protected from the same mistake by the Compose file, which
     * refuses to start without the variable.
     */
    public function test_an_unset_canonical_host_indexes_nothing(): void
    {
        config()->set('search.canonical_host', null);

        $this->get('/')
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Caddy compares this value literally when it serves a static file, so a
     * scheme or a path in it would make Laravel and the web server disagree
     * about which host is canonical. Refused rather than normalized, so the
     * mistake surfaces as an unindexed site instead of as noindex headers on
     * production's own images.
     */
    public function test_a_canonical_host_with_a_scheme_is_refused(): void
    {
        foreach (['https://localhost/', 'https://localhost', 'localhost/blog'] as $malformed) {
            config()->set('search.canonical_host', $malformed);

            $this->get('/')
                ->assertOk()
                ->assertSee('noindex, nofollow', false);
        }
    }

    /**
     * Nothing this value is compared against carries a port: `getHost()` drops
     * it, and so does `parse_url` in the APP_URL assertion. A port could
     * therefore only ever fail to match — silently, with a value that reads
     * correctly.
     */
    public function test_a_canonical_host_with_a_port_is_refused(): void
    {
        config()->set('search.canonical_host', 'localhost:8011');

        $this->get('/')
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }

    public function test_a_bare_hostname_is_accepted(): void
    {
        config()->set('search.canonical_host', 'localhost');

        $this->get('/')->assertOk()->assertDontSee('noindex, nofollow', false);
    }

    public function test_a_page_that_holds_itself_back_stays_noindexed_on_the_canonical_host(): void
    {
        $this->get('/templates')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
