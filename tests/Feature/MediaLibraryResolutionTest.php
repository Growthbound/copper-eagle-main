<?php

namespace Tests\Feature;

use App\Content\MediaIndex;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Proves a page renders against whichever media library is configured, without
 * the page itself changing.
 *
 * The point of the indirection is that the library can move — dev site today,
 * a CDN or this install tomorrow — and no managed document has to be rewritten.
 * That only holds if the rewrite happens at render time, so it is asserted at
 * render time.
 */
class MediaLibraryResolutionTest extends TestCase
{
    public function test_a_media_path_renders_against_the_configured_library(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $html = $this->renderGallery();

        $this->assertStringContainsString('src="https://library.example/media/43"', $html);
    }

    public function test_a_media_path_is_served_from_this_install_when_no_library_is_configured(): void
    {
        config()->set('media.base_url', '');

        $html = $this->renderGallery();

        $this->assertStringContainsString('src="'.url('/media/43').'"', $html);
        $this->assertStringNotContainsString('library.example', $html);
    }

    public function test_changing_the_library_does_not_require_touching_content(): void
    {
        config()->set('media.base_url', 'https://first.example');
        $first = $this->renderGallery();

        config()->set('media.base_url', 'https://second.example');
        $second = $this->renderGallery();

        $this->assertStringContainsString('https://first.example/media/43', $first);
        $this->assertStringContainsString('https://second.example/media/43', $second);
    }

    public function test_application_assets_are_never_rewritten_to_the_media_library(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $this->assertSame(
            '/build/assets/app.css',
            MediaIndex::resolve('/build/assets/app.css', 'https://library.example'),
        );

        $this->assertStringNotContainsString(
            'library.example/build',
            $this->get('/')->assertOk()->getContent(),
        );
    }

    private function renderGallery(): string
    {
        return Blade::render(
            '<x-blocks.photo-grid.gallery heading="Gallery" :images="$images" />',
            [
                'images' => [[
                    'src' => '/media/43',
                    'alt' => 'Interior of a duct plenum after coating',
                    'width' => 601,
                    'height' => 802,
                ]],
            ],
        );
    }
}
