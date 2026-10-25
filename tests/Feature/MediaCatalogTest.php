<?php

namespace Tests\Feature;

use App\Enums\MediaKind;
use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_uploads_default_to_image(): void
    {
        $media = Media::query()->create($this->existingUploadAttributes());

        $this->assertSame(MediaKind::Image, $media->kind);
        $this->assertNull($media->source_url);
    }

    public function test_remote_videos_store_the_complete_catalog_metadata(): void
    {
        $media = Media::query()->create($this->remoteVideoAttributes());

        $this->assertSame(MediaKind::Video, $media->kind);
        $this->assertSame('Homepage hero video', $media->title);
        $this->assertSame('home-hero-video', $media->handle);
        $this->assertSame('https://cdn.example.com/home-hero.mp4', $media->source_url);
        $this->assertSame(str_repeat('a', 64), $media->source_hash);
        $this->assertSame(str_repeat('b', 64), $media->checksum);
    }

    public function test_media_can_be_resolved_by_handle(): void
    {
        $media = Media::query()->create($this->remoteVideoAttributes([
            'handle' => 'home-hero-video',
        ]));

        $this->assertTrue($media->is(Media::forHandle('home-hero-video')));
    }

    public function test_duplicate_handles_are_rejected(): void
    {
        Media::query()->create($this->remoteVideoAttributes());

        $this->expectException(QueryException::class);

        Media::query()->create($this->remoteVideoAttributes([
            'path' => 'media/remote/home-hero-copy.mp4',
            'source_hash' => str_repeat('c', 64),
        ]));
    }

    public function test_duplicate_source_hashes_are_rejected(): void
    {
        Media::query()->create($this->remoteVideoAttributes());

        $this->expectException(QueryException::class);

        Media::query()->create($this->remoteVideoAttributes([
            'path' => 'media/remote/home-hero-copy.mp4',
            'handle' => 'home-hero-video-copy',
        ]));
    }

    /**
     * @return array<string, int|string|null>
     */
    private function existingUploadAttributes(array $overrides = []): array
    {
        return array_merge([
            'path' => 'media/uploads/duct-before.jpg',
            'original_name' => 'duct-before.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            'alt' => null,
        ], $overrides);
    }

    /**
     * @return array<string, int|string|MediaKind|null>
     */
    private function remoteVideoAttributes(array $overrides = []): array
    {
        return $this->existingUploadAttributes(array_merge([
            'path' => 'media/remote/home-hero.mp4',
            'original_name' => 'home-hero.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2_000_000,
            'width' => 1920,
            'height' => 1080,
            'kind' => MediaKind::Video,
            'title' => 'Homepage hero video',
            'handle' => 'home-hero-video',
            'source_url' => 'https://cdn.example.com/home-hero.mp4',
            'source_hash' => str_repeat('a', 64),
            'checksum' => str_repeat('b', 64),
        ], $overrides));
    }
}
