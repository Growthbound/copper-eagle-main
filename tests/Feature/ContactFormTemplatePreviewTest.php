<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactFormTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
    }

    public function test_local_preview_renders_the_split_contact_form_as_noindex(): void
    {
        $this->get('/_templates/contact-form')
            ->assertOk()
            ->assertSee('Split contact form template')
            ->assertSee('Template example — not public contact copy')
            ->assertSee('data-contact-form-variant="split"', false)
            ->assertSee('data-quote-form-variant="split"', false)
            ->assertSee('/images/home/technician-maintenance.jpg')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/contact-form');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/contact-form')->assertNotFound();
    }
}
