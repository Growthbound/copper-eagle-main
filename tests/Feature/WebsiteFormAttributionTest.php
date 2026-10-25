<?php

namespace Tests\Feature;

use App\Content\BlockRegistry;
use App\Support\Attribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesWebsiteFormFixtures;
use Tests\TestCase;

class WebsiteFormAttributionTest extends TestCase
{
    use PublishesWebsiteFormFixtures;
    use RefreshDatabase;

    private const INQUIRY_FORM = 'ExampleInquiryForm01';

    protected function setUp(): void
    {
        parent::setUp();
        config(['website-forms.provider' => 'highlevel', 'website-forms.origin' => 'https://api.leadconnectorhq.com',
            'website-forms.forms.inquiry' => self::INQUIRY_FORM]);

        // A visitor lands on one page and enquires from another.
        $this->publishFormPage('landing', 'Welcome');
        $this->publishFormPage('contact-us', 'Request');
    }

    public function test_campaign_survives_navigation_without_forwarding_unrelated_query_data(): void
    {
        $this->get('/landing/?utm_source=google&utm_campaign=summer&gclid=click-123&email=private@example.test');
        $response = $this->get('/contact-us/?utm_source=mail&token=private-token&refferral_source=someone')->assertOk();
        $url = $this->iframeUrl($response->getContent());
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(['utm_source' => 'mail', 'utm_campaign' => 'summer', 'gclid' => 'click-123'], $query);
        $this->assertStringNotContainsString('private', $url);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_invalid_campaign_shapes_are_not_forwarded_and_sessions_are_isolated(): void
    {
        $first = $this->get('/landing/?utm_source=first')->assertOk();
        $this->assertStringContainsString('utm_source=first', $this->iframeUrl($first->getContent()));
        session()->flush();
        $response = $this->get('/landing/?utm_source[]=bad&fbclid='.str_repeat('a', 300).'&utm_medium=email')->assertOk();
        parse_str(parse_url($this->iframeUrl($response->getContent()), PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('utm_source', $query);
        $this->assertArrayNotHasKey('fbclid', $query);
        $this->assertSame('email', $query['utm_medium']);
        $next = $this->get('/contact-us/')->assertOk();
        $this->assertStringNotContainsString('fbclid=', $this->iframeUrl($next->getContent()));
    }

    public function test_form_purpose_is_governed_and_provider_settings_are_not_content(): void
    {
        $registry = app(BlockRegistry::class);
        foreach (['cta/quote-form', 'cta/quote-form-split'] as $type) {
            $this->assertTrue($registry->validate($type, ['heading' => 'Feedback', 'purpose' => 'feedback'])->isValid());
            $this->assertFalse($registry->validate($type, ['heading' => 'Request', 'purpose' => 'arbitrary'])->isValid());
            $this->assertFalse($registry->validate($type, ['heading' => 'Request', 'formId' => 'evil'])->isValid());
        }
    }

    public function test_direct_session_upgrades_and_all_supported_campaign_keys_survive_navigation(): void
    {
        $this->get('/landing/');
        $fields = array_fill_keys([...Attribution::UTM_FIELDS, ...Attribution::CLICK_FIELDS], 'campaign-value');
        $this->get('/landing/?'.http_build_query($fields));
        $response = $this->get('/contact-us/')->assertOk();
        parse_str(parse_url($this->iframeUrl($response->getContent()), PHP_URL_QUERY), $actual);
        $this->assertSame($fields, $actual);
    }

    public function test_invalid_first_touch_does_not_lock_out_a_later_valid_campaign(): void
    {
        foreach ([str_repeat('x', 300), "bad\nsource"] as $invalid) {
            session()->flush();
            $this->get('/landing/?'.http_build_query(['utm_source' => $invalid]))->assertOk();
            $this->get('/landing/?utm_source=valid-campaign')->assertOk();
            $response = $this->get('/contact-us/')->assertOk();
            $this->assertStringContainsString('utm_source=valid-campaign', $this->iframeUrl($response->getContent()));
        }
    }

    private function iframeUrl(string $html): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        foreach ($document->getElementsByTagName('iframe') as $iframe) {
            if ($iframe->getAttribute('data-form-id') === self::INQUIRY_FORM) {
                return $iframe->getAttribute('src');
            }
        }
        $this->fail('The public inquiry embed is missing.');
    }
}
