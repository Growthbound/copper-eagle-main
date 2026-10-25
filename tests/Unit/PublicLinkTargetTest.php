<?php

namespace Tests\Unit;

use App\Content\AreaRepository;
use App\Content\Page;
use App\Content\PageRepository;
use App\Content\PublicLinkTarget;
use App\Content\RichContent;
use App\Content\ServiceRepository;
use Mockery;
use Tests\TestCase;

class PublicLinkTargetTest extends TestCase
{
    public function test_combined_route_must_be_served_or_have_an_explicit_page_override(): void
    {
        $pages = Mockery::mock(PageRepository::class);
        $services = Mockery::mock(ServiceRepository::class);
        $areas = Mockery::mock(AreaRepository::class);
        $pages->shouldReceive('find')
            ->once()
            ->with('services/limited-service/unserved-area')
            ->andReturnNull();
        $pages->shouldReceive('find')
            ->once()
            ->with('services/limited-service/unserved-area')
            ->andReturn(new Page(
                slug: 'services/limited-service/unserved-area',
                title: 'Explicit service area page',
                description: null,
                noindex: false,
                blocks: [],
                sourcePath: '/content/pages/services/limited-service/unserved-area.json',
            ));

        $target = new PublicLinkTarget(
            app(RichContent::class),
            $pages,
            $services,
            $areas,
        );

        $this->assertFalse(
            $target->exists('/services/limited-service/unserved-area'),
        );
        $this->assertTrue(
            $target->exists('/services/limited-service/unserved-area'),
        );
    }

    public function test_same_site_absolute_urls_follow_internal_destination_validation(): void
    {
        config()->set('app.url', 'https://production.example');
        $pages = Mockery::mock(PageRepository::class);
        $services = Mockery::mock(ServiceRepository::class);
        $areas = Mockery::mock(AreaRepository::class);
        $pages->shouldReceive('find')
            ->once()
            ->with('missing')
            ->andReturnNull();
        $pages->shouldReceive('find')
            ->once()
            ->with('about')
            ->andReturn(new Page(
                slug: 'about',
                title: 'About',
                description: null,
                noindex: false,
                blocks: [],
                sourcePath: '/content/pages/about.json',
            ));

        $target = new PublicLinkTarget(
            app(RichContent::class),
            $pages,
            $services,
            $areas,
        );

        $this->assertFalse($target->exists('https://production.example/missing'));
        $this->assertTrue($target->exists('https://production.example/about?ref=nav'));
        $this->assertTrue($target->exists('https://external.example/anything'));
        $this->assertTrue($target->exists('https://production.example:8443/docs'));
        $this->assertTrue($target->exists('http://production.example/docs'));
    }

    public function test_retiring_paths_are_compared_after_consistent_path_normalization(): void
    {
        config()->set('app.url', 'https://production.example');
        $pages = Mockery::mock(PageRepository::class);
        $services = Mockery::mock(ServiceRepository::class);
        $areas = Mockery::mock(AreaRepository::class);
        $pages->shouldNotReceive('find');
        $services->shouldNotReceive('find');
        $areas->shouldNotReceive('find');

        $target = new PublicLinkTarget(
            app(RichContent::class),
            $pages,
            $services,
            $areas,
        );

        $this->assertFalse($target->exists('/primary/', ['/primary']));
        $this->assertFalse($target->exists(
            'https://production.example/primary/?ref=navigation',
            ['/primary/'],
        ));
    }

    public function test_internal_targets_reject_empty_path_segments(): void
    {
        $pages = Mockery::mock(PageRepository::class);
        $services = Mockery::mock(ServiceRepository::class);
        $areas = Mockery::mock(AreaRepository::class);
        $pages->shouldNotReceive('find');
        $services->shouldNotReceive('find');
        $areas->shouldNotReceive('find');

        $target = new PublicLinkTarget(
            app(RichContent::class),
            $pages,
            $services,
            $areas,
        );

        foreach ([
            '/services//example-service',
            '/areas//springfield',
            '/media//1',
            '/about//',
        ] as $path) {
            $this->assertFalse($target->exists($path), $path);
        }
    }
}
