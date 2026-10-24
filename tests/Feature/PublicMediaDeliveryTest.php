<?php

namespace Tests\Feature;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Services\Media\RemoteMediaImporter;
use App\ValueObjects\MediaImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class PublicMediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_catalogued_public_image_is_served_from_its_database_backed_url(): void
    {
        $path = 'media/imported/2026/07/duct.jpg';
        Storage::disk('public')->put($path, 'image bytes');
        $media = $this->media($path, 'image/jpeg');

        $this->assertSame(url('/media/'.$media->id), $media->url());

        $this->get($media->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'max-age=86400, public');
    }

    public function test_a_catalogued_public_video_supports_http_range_requests(): void
    {
        $path = 'media/imported/2026/07/hero.mp4';
        Storage::disk('public')->put($path, '0123456789');
        $media = $this->media($path, 'video/mp4', MediaKind::Video);

        $this->withHeader('Range', 'bytes=0-3')
            ->get($media->url())
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Range', 'bytes 0-3/10');
    }

    public function test_an_uncatalogued_public_disk_file_is_not_exposed(): void
    {
        $path = 'media/imported/2026/07/orphan.jpg';
        Storage::disk('public')->put($path, 'orphan bytes');

        $this->get('/media/999999')->assertNotFound();
    }

    public function test_a_catalogue_row_with_a_missing_binary_returns_not_found(): void
    {
        $path = 'media/imported/2026/07/missing.jpg';
        $media = $this->media($path, 'image/jpeg');

        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('restore');
        });

        $this->get($media->url())->assertNotFound();
    }

    public function test_a_missing_imported_binary_is_restored_before_it_is_served(): void
    {
        $path = 'media/imported/2026/07/restored.jpg';
        $sourceUrl = 'https://media.example/restored.jpg';
        $media = $this->media($path, 'image/jpeg', sourceUrl: $sourceUrl);

        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock) use ($media, $path, $sourceUrl): void {
            $mock->shouldReceive('restore')
                ->once()
                ->withArgs(fn (Media $candidate): bool => $candidate->is($media))
                ->andReturnUsing(function () use ($media, $path, $sourceUrl): MediaImportResult {
                    Storage::disk('public')->put($path, 'restored image bytes');

                    return new MediaImportResult(
                        status: 'existing',
                        sourceUrl: $sourceUrl,
                        media: $media,
                        message: 'The missing stored file was restored.',
                    );
                });
        });

        $this->get($media->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        Storage::disk('public')->assertExists($path);
    }

    private function media(
        string $path,
        string $mimeType,
        MediaKind $kind = MediaKind::Image,
        ?string $sourceUrl = null,
    ): Media {
        return Media::query()->create([
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => $mimeType,
            'size' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : 0,
            'width' => $kind === MediaKind::Image ? 800 : null,
            'height' => $kind === MediaKind::Image ? 600 : null,
            'kind' => $kind,
            'source_url' => $sourceUrl,
            'source_hash' => $sourceUrl === null ? null : hash('sha256', $sourceUrl),
        ]);
    }
}
