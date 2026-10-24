<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomepageTest extends TestCase
{
    public function test_homepage_renders_every_required_section_without_template_debris(): void
    {
        $response = $this->get('/')->assertOk()->assertSee((string) config('site.name'));

        /*
         * Every heading the page file declares has to reach the page. Read from
         * the file rather than listed here, so rewriting the copy cannot make
         * this fail while the sections it is actually checking still render.
         */
        foreach ($this->homepageHeadings() as $heading) {
            $response->assertSee($heading);
        }

        $response->assertSee('id="quote"', false);

        // Debris from the purchased template this design started from.
        $response->assertDontSee('HomeMaster')
            ->assertDontSee('Get This Template')
            ->assertDontSee('William Parker')
            ->assertDontSee('href="#"', false);
    }

    public function test_homepage_hides_empty_contact_values(): void
    {
        config()->set('site.phone', '');
        config()->set('site.email', '');
        config()->set('site.address', '');

        $this->get('/')
            ->assertDontSee('mailto:', false)
            ->assertDontSee('tel:', false)
            ->assertDontSee('data-contact-address', false);
    }

    public function test_homepage_exposes_the_favicon_set(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.asset('favicon.ico').'"', false)
            ->assertSee('href="'.asset('favicon.png').'"', false)
            ->assertSee('href="'.asset('apple-touch-icon.png').'"', false);

        foreach (['favicon.ico', 'favicon.png', 'apple-touch-icon.png'] as $icon) {
            $this->assertFileExists(public_path($icon));
        }
    }

    public function test_homepage_uses_only_local_images(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('images.pexels.com', $content);
        $this->assertStringNotContainsString('website-files.com', $content);
        // The stock photos belong to the template gallery, never a public page.
        $this->assertStringNotContainsString('/images/home/', $content);
    }

    public function test_homepage_renders_verified_optional_business_details(): void
    {
        config()->set('site.phone', '+1 555 555 0100');
        config()->set('site.email', 'hello@example.test');
        config()->set('site.address', '123 Example Street');
        config()->set('site.social_links', [
            ['label' => 'Instagram', 'url' => 'https://www.instagram.com/example'],
        ]);

        $this->get('/')
            ->assertOk()
            // The call component strips formatting so the href is dialable.
            ->assertSee('href="tel:+15555550100"', false)
            ->assertSee('href="mailto:hello@example.test"', false)
            ->assertSee('data-contact-address', false)
            ->assertSee('href="https://www.instagram.com/example"', false);
    }

    public function test_laravel_health_endpoint_is_reachable(): void
    {
        $this->get('/up')->assertOk();
    }

    /**
     * The homepage headline, read from the page file rather than hardcoded.
     *
     * These tests are about publication precedence and page rendering, not
     * about the wording. Pinning the copy made every brand-voice edit break
     * tests that have nothing to do with copy.
     */
    private function homepageHeadline(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $page['blocks'][0]['props']['heading'];
    }

    private function homepageTitle(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $page['title'];
    }

    /**
     * Every heading the homepage file declares, in file order.
     *
     * @return list<string>
     */
    private function homepageHeadings(): array
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $headings = [];

        foreach ($page['blocks'] as $block) {
            foreach (['heading', 'label'] as $field) {
                if (filled($block['props'][$field] ?? null)) {
                    $headings[] = $block['props'][$field];
                }
            }
        }

        return $headings;
    }
}
