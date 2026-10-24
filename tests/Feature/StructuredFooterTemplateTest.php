<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StructuredFooterTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
    }

    public function test_it_renders_verified_business_details_and_link_columns(): void
    {
        config()->set('site.phone', '+1 555 555 0100');
        config()->set('site.email', 'hello@example.test');
        config()->set('site.address', '123 Example Street');
        config()->set('site.social_links', [
            ['label' => 'Instagram', 'url' => 'https://instagram.com/example'],
        ]);

        $html = Blade::render(
            '<x-site-footers.structured :company-links="$company" :resource-links="$resources" />',
            [
                'company' => [['label' => 'About', 'href' => '/about']],
                'resources' => [['label' => 'Homeowner guide', 'href' => '/resources/homeowner-guide']],
            ],
        );

        $this->assertStringContainsString('data-footer-variant="structured"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', $html);
        $this->assertStringContainsString('href="tel:+1 555 555 0100"', $html);
        $this->assertStringContainsString('data-contact-address', $html);
        $this->assertStringContainsString('Homeowner guide', $html);
        $this->assertStringContainsString('Instagram', $html);
        $this->assertStringContainsString('aria-label="Company"', $html);
        $this->assertStringContainsString('aria-label="Resources"', $html);
    }

    public function test_it_omits_blank_contact_social_and_malformed_links(): void
    {
        config()->set('site.phone', '');
        config()->set('site.email', '');
        config()->set('site.address', '');
        config()->set('site.social_links', []);

        $html = Blade::render(
            '<x-site-footers.structured :company-links="$links" :resource-links="[]" />',
            ['links' => [
                ['label' => '', 'href' => '/blank-label'],
                ['label' => 'Blank destination', 'href' => ''],
                ['label' => 'Unsafe destination', 'href' => 'javascript:alert(1)'],
            ]],
        );

        $this->assertStringNotContainsString('structured-footer-contact', $html);
        $this->assertStringNotContainsString('structured-footer-social', $html);
        $this->assertStringNotContainsString('Blank destination', $html);
        $this->assertStringNotContainsString('Unsafe destination', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_escapes_link_labels_and_uses_safe_destinations(): void
    {
        $html = Blade::render(
            '<x-site-footers.structured :company-links="$links" :resource-links="[]" />',
            ['links' => [[
                'label' => '<script>alert("label")</script>',
                'href' => '/safe?value=<unsafe>',
            ]]],
        );

        $this->assertStringContainsString('&lt;script&gt;alert(&quot;label&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('href="/safe?value=&lt;unsafe&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_layout_can_hide_only_the_global_footer(): void
    {
        $html = Blade::render(
            '<x-layouts.marketing title="Footer preview" :show-footer="false"><p>Preview body</p></x-layouts.marketing>',
        );

        $this->assertStringContainsString('Preview body', $html);
        $this->assertStringNotContainsString('class="site-footer"', $html);
    }

    public function test_the_existing_global_footer_contract_remains_available(): void
    {
        $html = Blade::render('<x-site-footer />');

        $this->assertStringContainsString('class="site-footer"', $html);
        $this->assertStringNotContainsString('data-footer-variant="structured"', $html);
    }
}
