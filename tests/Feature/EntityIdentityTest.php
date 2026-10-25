<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Support\SiteLogo;
use App\Support\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

/**
 * The pieces that let a search engine or an AI answer resolve this site into a
 * single, pictured business.
 *
 * A third-party readiness checker reported the Organization name, FAQPage and
 * Article schema as missing. All three were present — it was matching
 * `@type == "Organization"` literally and never opened an article. The
 * assertions here pin the parts that were genuinely absent, and equally pin the
 * two things that must not be "fixed" into a worse state.
 */
class EntityIdentityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFixtureContent;

    protected function setUp(): void
    {
        parent::setUp();

        SiteLogo::flush();
        $this->setUpFixtureContent();
        $this->fixtureHomePage();
    }

    protected function tearDown(): void
    {
        SiteLogo::flush();
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    /**
     * Google's Organization logo has a floor of 112x112. A square mark the
     * owner uploads clears it, so the entity declares it.
     */
    public function test_the_business_declares_a_logo_that_clears_the_minimum(): void
    {
        $logo = $this->uploadLogo(512, 512);

        $business = $this->businessNode();

        $this->assertSame('ImageObject', $business['logo']['@type']);
        $this->assertSame($logo->url(), $business['logo']['url']);
        $this->assertGreaterThanOrEqual(112, $business['logo']['width']);
        $this->assertGreaterThanOrEqual(112, $business['logo']['height']);
    }

    /**
     * A header wordmark 80 pixels tall is below that floor. Declaring it as the
     * entity logo produces markup Google ignores — which is worse than
     * declaring none, because it reads as done.
     */
    public function test_a_wordmark_below_the_minimum_is_not_declared_as_the_logo(): void
    {
        config()->set('theme.logos', []);
        $this->uploadLogo(380, 80);

        $this->assertArrayNotHasKey('logo', $this->businessNode());
    }

    /**
     * `image` on a LocalBusiness is meant to be a picture of the business, not
     * its wordmark. Nothing in the media library is confirmed as a
     * representative photograph, so the field stays absent rather than pointing
     * at a logo.
     */
    public function test_the_business_does_not_pass_its_logo_off_as_a_photograph(): void
    {
        $this->uploadLogo(512, 512);

        $this->assertArrayNotHasKey('image', $this->businessNode());
    }

    /**
     * A specific LocalBusiness subtype is still an Organization. Replacing it
     * with a bare `Organization` to satisfy a literal type check would describe
     * the business less precisely than its configuration does.
     */
    public function test_the_business_keeps_its_specific_type_and_its_name(): void
    {
        config()->set('site.business_type', 'HomeAndConstructionBusiness');

        $business = $this->businessNode();

        $this->assertSame('HomeAndConstructionBusiness', $business['@type']);
        $this->assertSame(config('site.name'), $business['name']);
    }

    /**
     * Google does not support review markup a business supplies about itself.
     * A rating belongs on the Business Profile, where Google already reads it,
     * and the site's own testimonials are unevidenced besides.
     */
    public function test_the_business_makes_no_self_serving_rating_claim(): void
    {
        $business = $this->businessNode();

        $this->assertArrayNotHasKey('aggregateRating', $business);
        $this->assertArrayNotHasKey('review', $business);
    }

    /**
     * The code path exists and fills from configuration. Nothing may be
     * asserted about which profiles are real until the owner confirms them, so
     * this proves the wiring rather than inventing a profile.
     */
    public function test_social_profiles_are_declared_only_when_configured(): void
    {
        config()->set('site.social_links', []);

        $this->assertArrayNotHasKey('sameAs', $this->businessNode());

        config()->set('site.social_links', [
            ['label' => 'Facebook', 'url' => 'https://www.facebook.com/example'],
        ]);

        $this->assertSame(
            ['https://www.facebook.com/example'],
            $this->businessNode()['sameAs'],
        );
    }

    public function test_every_page_emits_a_shareable_card_without_opting_in(): void
    {
        // The fallback for a page that has no summary of its own.
        config()->set('site.description', 'A fictional business used by the test suite.');
        $this->uploadLogo(512, 512);

        foreach (['/', '/services', '/areas', '/blog/'] as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            foreach (['og:type', 'og:site_name', 'og:title', 'og:description', 'og:url', 'og:image'] as $tag) {
                $this->assertStringContainsString(
                    '<meta property="'.$tag.'"',
                    (string) $content,
                    "{$path} is missing {$tag}.",
                );
            }

            $this->assertStringContainsString('name="twitter:card"', (string) $content);
        }
    }

    /**
     * Without an uploaded logo there is no image to share, and the card says
     * so rather than borrowing a placeholder that is not the brand's mark.
     */
    public function test_a_site_without_a_logo_shares_no_placeholder_image(): void
    {
        config()->set('theme.logos', []);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta property="og:title"', false)
            ->assertDontSee('<meta property="og:image"', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    /**
     * The large card has a floor of 300x157 and expects roughly 2:1. A 380x80
     * wordmark fits neither, so declaring the large card for it gets the image
     * dropped rather than scaled.
     */
    public function test_the_logo_fallback_uses_the_card_type_it_actually_fits(): void
    {
        $this->uploadLogo(380, 80);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertDontSee('summary_large_image', false);
    }

    public function test_a_page_with_a_real_social_image_gets_the_large_card(): void
    {
        $rendered = (string) $this->blade(
            '<x-layouts.marketing :open-graph="$og">body</x-layouts.marketing>',
            ['og' => ['image' => [
                'src' => '/media/16',
                'alt' => 'A completed service visit',
                'width' => 1200,
                'height' => 630,
            ]]],
        );

        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $rendered);
    }

    /**
     * The share card falls back to the header mark, which is the right asset
     * for a link preview even when it is the wrong one for the entity logo. The
     * two answer different questions and are allowed to differ.
     */
    public function test_the_share_card_falls_back_to_the_header_mark(): void
    {
        config()->set('theme.logos', []);
        $logo = $this->uploadLogo(380, 80);

        $content = (string) $this->get('/')->assertOk()->getContent();

        preg_match('/<meta property="og:image" content="([^"]+)"/', $content, $matches);

        $this->assertNotEmpty($matches, 'No og:image on the homepage.');
        $this->assertSame($logo->url(), $matches[1]);
        $this->assertArrayNotHasKey('logo', $this->businessNode());
    }

    /**
     * With no uploaded logo, the brand's own files stand in: the horizontal
     * mark for the header and share card, the near-square stacked mark for the
     * entity logo, where a wordmark would be ignored.
     */
    public function test_the_bundled_brand_logos_stand_in_until_one_is_uploaded(): void
    {
        $content = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(asset(config('theme.logos.horizontal.src')), $content);
        $this->assertStringContainsString('<meta property="og:image" content="'.asset(config('theme.logos.horizontal.src')).'"', $content);
        $this->assertSame(asset(config('theme.logos.stacked.src')), $this->businessNode()['logo']['url'] ?? $this->businessNode()['logo'] ?? null);
    }

    /**
     * A square logo clears the 300x157 floor and still gets cropped or dropped
     * as a large card, because that card wants roughly 2:1.
     */
    public function test_a_square_image_does_not_claim_the_large_card(): void
    {
        $rendered = (string) $this->blade(
            '<x-layouts.marketing :open-graph="$og">body</x-layouts.marketing>',
            ['og' => ['image' => [
                'src' => '/media/1',
                'alt' => 'A square logo',
                'width' => 512,
                'height' => 512,
            ]]],
        );

        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $rendered);
        $this->assertStringNotContainsString('summary_large_image', $rendered);
    }

    private function uploadLogo(int $width, int $height): Media
    {
        $media = Media::create([
            'path' => 'media/fixture-logo.png',
            'original_name' => 'fixture-logo.png',
            'mime_type' => 'image/png',
            'size' => 4096,
            'width' => $width,
            'height' => $height,
        ]);

        config()->set('site.logo_media_id', $media->id);
        SiteLogo::flush();

        return $media;
    }

    /**
     * @return array<string, mixed>
     */
    private function businessNode(): array
    {
        $content = (string) $this->get('/')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);

        $this->assertNotEmpty($matches, 'No JSON-LD on the homepage.');

        foreach (json_decode(html_entity_decode($matches[1]), true)['@graph'] as $node) {
            if (($node['@id'] ?? null) === StructuredData::businessId()) {
                return $node;
            }
        }

        $this->fail('No business node on the homepage.');
    }
}
