<?php

namespace Tests\Feature;

use App\Content\UrlDispositionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Privacy Policy and Terms pages as they ship.
 *
 * Their text comes from the company and is not ours to reword. Its SMS consent
 * paragraph quotes https://www.coppereagleplumbing.com/privacy, but this site's
 * privacy page lives at /privacy-policy. So the quoted address has to redirect,
 * and the long address has to wrap on a phone instead of widening the page.
 */
class CopperEagleLegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private const QUOTED_ADDRESS = 'https://www.coppereagleplumbing.com/privacy.';

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

    public function test_the_address_the_legal_text_quotes_redirects_to_the_privacy_policy(): void
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

    public function test_the_legal_text_still_quotes_the_address_unchanged(): void
    {
        foreach (['/privacy-policy/', '/terms-and-conditions/'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee(self::QUOTED_ADDRESS, false);
        }
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
