<?php

namespace Tests\Feature;

use App\Content\UrlDispositionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Privacy Policy and Terms pages as they ship.
 *
 * Their text comes from the company and is not ours to reword: both are copied
 * word for word from the live site (Terms effective, and Privacy Policy last
 * updated, October 24, 2026). The Privacy Policy's SMS consent message now
 * quotes /privacy-policy, but messages sent before that quoted
 * https://www.coppereagleplumbing.com/privacy, so that address still has to
 * redirect. Long addresses have to wrap on a phone instead of widening the page.
 */
class CopperEagleLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private const QUOTED_ADDRESS = 'https://www.coppereagleplumbing.com/privacy-policy.';

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

    public function test_the_address_older_messages_quote_redirects_to_the_privacy_policy(): void
    {
        foreach (['/privacy', '/privacy/'] as $path) {
            $response = $this->get($path)->assertStatus(301);

            // assertRedirect() trims the trailing slash, so compare the path.
            $this->assertSame(
                '/privacy-policy/',
                parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            );
        }

        $this->get('/privacy-policy/')->assertOk();
    }

    public function test_the_privacy_policy_quotes_its_own_address_and_links_to_the_terms(): void
    {
        $html = $this->get('/privacy-policy/')
            ->assertOk()
            ->assertSee('Copper Eagle Plumbing Privacy Policy')
            ->assertSee(self::QUOTED_ADDRESS, false)
            ->getContent();

        // The footer links to both legal pages on every page, so match the
        // link by the policy's own link text.
        $this->assertMatchesRegularExpression('#<a href="/terms-and-conditions/"[^>]*>\s*coppereagleplumbing\.com/terms-and-conditions\s*</a>#', $html);
    }

    /**
     * The Terms page once carried a copy of the Privacy Policy. It must carry
     * the SMS program terms that the contact form's consent boxes point to.
     */
    public function test_the_terms_page_carries_the_sms_terms_and_links_to_the_privacy_policy(): void
    {
        $html = $this->get('/terms-and-conditions/')
            ->assertOk()
            ->assertSee('Copper Eagle Plumbing Terms and Conditions')
            ->assertSee('Program name: Copper Eagle Plumbing Customer Text Messages.')
            ->assertDontSee('Copper Eagle Plumbing Privacy Policy')
            ->getContent();

        $this->assertMatchesRegularExpression('#<a href="/privacy-policy/"[^>]*>\s*coppereagleplumbing\.com/privacy-policy\s*</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="/contact-copper-eagle-plumbing/"[^>]*>\s*coppereagleplumbing\.com/contact-copper-eagle-plumbing\s*</a>#', $html);
    }

    /**
     * A bare address has no spaces to break at. Without this rule it overflows
     * the reading column, and a 390px phone scrolls the whole page sideways.
     */
    public function test_rich_text_lets_a_long_address_wrap(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/app.css')));

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', (string) $css, $rules, PREG_SET_ORDER);

        $wraps = collect($rules)->contains(function (array $rule): bool {
            $selectors = array_map('trim', explode(',', $rule[1]));

            return in_array('.rich-content-reading', $selectors, true)
                && preg_match('/overflow-wrap:\s*anywhere\s*;/', $rule[2]) === 1;
        });

        $this->assertTrue($wraps, '.rich-content-reading must set overflow-wrap: anywhere.');
    }
}
