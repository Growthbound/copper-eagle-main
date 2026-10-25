<?php

namespace Tests\Feature;

use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

/**
 * The phone number is the conversion path for a local service business, so it
 * has to be reachable from wherever a reader happens to stop.
 *
 * A visitor who scrolls half a page and decides to call should not have to hunt
 * for the number, and on a phone they should not have to scroll at all.
 */
class CallToActionReachTest extends TestCase
{
    use IsolatesManagedContent;
    use UsesFixtureContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixtureContent();

        config()->set('site.phone', '(555) 555-0100');
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    public function test_every_marketing_page_offers_a_call_from_the_header(): void
    {
        foreach ($this->marketingPages() as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                'header-call',
                $content,
                "No header call link on {$path}.",
            );
        }
    }

    public function test_every_marketing_page_offers_a_call_without_scrolling_on_a_phone(): void
    {
        foreach ($this->marketingPages() as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                'sticky-mobile-call',
                $content,
                "No sticky mobile call bar on {$path}.",
            );
        }
    }

    public function test_every_call_link_points_at_the_configured_number(): void
    {
        foreach ($this->marketingPages() as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            preg_match_all('/href="tel:([^"]+)"/', $content, $matches);

            $this->assertSame(
                ['+15555550100'],
                array_values(array_unique($matches[1])),
                "A hard-coded phone link on {$path} would survive a call-tracking number change.",
            );
        }
    }

    public function test_nothing_calls_a_number_the_owner_has_not_set(): void
    {
        config()->set('site.phone', '');

        $content = $this->get($this->contactPage())->assertOk()->getContent();

        $this->assertStringNotContainsString('header-call', $content);
        $this->assertStringNotContainsString('sticky-mobile-call', $content);
        $this->assertStringNotContainsString('tel:', $content);
    }

    public function test_a_conversion_section_offers_a_call_alongside_the_form(): void
    {
        $chromeOnly = substr_count(
            (string) $this->get($this->fixtureHomePage())->assertOk()->getContent(),
            'tel:+15555550100',
        );
        $content = (string) $this->get($this->contactPage())->assertOk()->getContent();

        // The quote form and the closing panel are where intent is highest.
        $this->assertStringContainsString('cta-call', $content);
        $this->assertGreaterThan(
            $chromeOnly,
            substr_count($content, 'tel:+15555550100'),
            'A reader who reaches a conversion section should be able to call from it.',
        );
    }

    /**
     * One of each kind of public page: a block page, a conversion page, the
     * record hubs, a record-generated page of each kind, and the blog.
     *
     * @return array<int, string>
     */
    private function marketingPages(): array
    {
        return [
            $this->fixtureHomePage(),
            $this->contactPage(),
            '/services',
            $this->fixtureService(),
            '/areas',
            $this->fixtureArea(),
            '/blog/',
        ];
    }

    private function contactPage(): string
    {
        return $this->fixturePage('contact', [
            [
                'type' => 'cta/quote-form',
                'props' => ['heading' => 'Send us a message'],
            ],
            [
                'type' => 'cta/panel',
                'props' => [
                    'heading' => 'Ready when you are',
                    'cta' => ['label' => 'Send a message', 'href' => '#quote'],
                ],
            ],
        ]);
    }
}
