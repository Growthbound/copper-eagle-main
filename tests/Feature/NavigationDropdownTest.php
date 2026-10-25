<?php

namespace Tests\Feature;

use App\Content\SiteStructureRepository;
use Tests\Concerns\UsesFixtureContent;
use Tests\TestCase;

/**
 * Services and service areas open a menu of their own pages.
 *
 * The parent stays a real link to the hub, so the menu adds a shortcut rather
 * than taking one away. The menu opens on hover and on focus, so it works
 * without JavaScript and for anyone tabbing through the header.
 */
class NavigationDropdownTest extends TestCase
{
    use UsesFixtureContent;

    protected function tearDown(): void
    {
        $this->tearDownFixtureContent();

        parent::tearDown();
    }

    public function test_the_repository_keeps_child_links(): void
    {
        config()->set('site.navigation', [
            ['label' => 'Services', 'href' => '/services', 'children' => [
                ['label' => 'Fixture Service', 'href' => '/services/fixture-service'],
            ]],
            ['label' => 'Blog', 'href' => '/blog'],
        ]);

        $navigation = app(SiteStructureRepository::class)->navigation();

        $this->assertSame('/services/fixture-service', $navigation[0]['children'][0]['href']);
        $this->assertSame([], $navigation[1]['children'] ?? []);
    }

    public function test_a_child_without_a_label_or_href_is_dropped(): void
    {
        config()->set('site.navigation', [
            ['label' => 'Services', 'href' => '/services', 'children' => [
                ['label' => 'Good', 'href' => '/services/fixture-service'],
                ['label' => 'No href'],
                ['href' => '/services/fixture-other-service'],
            ]],
        ]);

        $children = app(SiteStructureRepository::class)->navigation()[0]['children'];

        $this->assertCount(1, $children);
        $this->assertSame('Good', $children[0]['label']);
    }

    public function test_nesting_stops_at_one_level(): void
    {
        config()->set('site.navigation', [
            ['label' => 'Services', 'href' => '/services', 'children' => [
                ['label' => 'Fixture Service', 'href' => '/services/fixture-service', 'children' => [
                    ['label' => 'Too deep', 'href' => '/services'],
                ]],
            ]],
        ]);

        $child = app(SiteStructureRepository::class)->navigation()[0]['children'][0];

        $this->assertArrayNotHasKey('children', $child);
    }

    public function test_the_header_renders_a_menu_for_each_parent_that_has_children(): void
    {
        $this->fixtureNavigation();

        $content = $this->get($this->fixtureHomePage())->assertOk()->getContent();

        $this->assertSame(2, substr_count($content, 'class="nav-has-menu"'));
        $this->assertStringContainsString('nav-menu', $content);

        // The parent still goes to the hub.
        $this->assertStringContainsString('href="/services"', $content);
        $this->assertStringContainsString('href="/areas"', $content);
        // And the children go straight to the pages.
        $this->assertStringContainsString('href="/services/fixture-service"', $content);
        $this->assertStringContainsString('href="/areas/fixture-area"', $content);
    }

    /**
     * The site's own menus, against the site's own pages: every internal link
     * the header and footer carry has to land on a page. The footer always
     * carries the homepage and the discovery files, so this holds a new
     * install to the same standard as a finished one.
     */
    public function test_every_menu_destination_is_a_page_that_exists(): void
    {
        $structure = app(SiteStructureRepository::class);
        $links = [...$structure->footer()['company'], ...$structure->footer()['resources']];

        foreach ($structure->navigation() as $item) {
            $links = [...$links, $item, ...($item['children'] ?? [])];
        }

        $paths = collect($links)
            ->pluck('href')
            ->filter(fn (string $href): bool => str_starts_with($href, '/') && ! str_starts_with($href, '//'))
            ->map(fn (string $href): string => (string) strtok($href, '#?'))
            ->unique();

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_the_mobile_menu_also_lists_the_children(): void
    {
        $this->fixtureNavigation();

        $content = $this->get($this->fixtureHomePage())->assertOk()->getContent();

        $this->assertStringContainsString('class="mobile-nav-child" href="/services/fixture-service"', $content);
    }

    /**
     * Two menus over record-generated pages, and a plain link without one.
     */
    private function fixtureNavigation(): void
    {
        $this->setUpFixtureContent();

        $service = $this->fixtureService();
        $area = $this->fixtureArea();

        config()->set('site.navigation', [
            ['label' => 'Services', 'href' => '/services', 'children' => [
                ['label' => 'Fixture Service', 'href' => $service],
            ]],
            ['label' => 'Service areas', 'href' => '/areas', 'children' => [
                ['label' => 'Fixture Area', 'href' => $area],
            ]],
            ['label' => 'Blog', 'href' => '/blog'],
        ]);
    }
}
