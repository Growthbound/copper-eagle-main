<?php

namespace Tests\Feature;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Services\Media\RemoteMediaFetcher;
use App\Services\Media\RemoteMediaImporter;
use App\ValueObjects\MediaImportItem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PDOException;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

class RemoteMediaImporterTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('media-import.max_bytes', 25 * 1024 * 1024);
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_first_import_creates_a_public_file_and_catalog_record_with_image_dimensions(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fixture = base_path('public/images/home/technician-maintenance.jpg');
        $fetcher = new FakeRemoteMediaFetcher([$sourceUrl => $fixture]);
        $item = new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: 'Technician at work',
            alt: 'Technician at work on a residential job',
            handle: 'home-technician',
        );

        $result = $this->importer($fetcher)->import($item);

        $dimensions = getimagesize($fixture);
        $this->assertSame('created', $result->status);
        $this->assertSame($sourceUrl, $result->sourceUrl);
        $this->assertNull($result->errorCode);
        $this->assertInstanceOf(Media::class, $result->media);
        $this->assertSame(MediaKind::Image, $result->media->kind);
        $this->assertSame('image/jpeg', $result->media->mime_type);
        $this->assertSame($dimensions[0], $result->media->width);
        $this->assertSame($dimensions[1], $result->media->height);
        $this->assertSame('Technician at work', $result->media->title);
        $this->assertSame('Technician at work on a residential job', $result->media->alt);
        $this->assertSame('home-technician', $result->media->handle);
        $this->assertSame(hash('sha256', file_get_contents($fixture)), $result->media->checksum);
        Storage::disk('public')->assertExists($result->media->path);
        $this->assertStringStartsWith('media/imported/'.now()->format('Y/m').'/', $result->media->path);
        $this->assertDatabaseHas('media', ['source_url' => $sourceUrl]);
        $this->assertFalse(file_exists($fetcher->lastTemporaryPath()));
    }

    public function test_container_resolution_injects_the_public_disk_when_the_application_default_is_private(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');
        $sourceUrl = 'https://media.example/container-resolved.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $this->app->instance(RemoteMediaFetcher::class, $fetcher);

        $result = $this->app->make(RemoteMediaImporter::class)
            ->import(new MediaImportItem($sourceUrl));

        $this->assertSame('created', $result->status);
        Storage::disk('public')->assertExists($result->media->path);
        Storage::disk('local')->assertMissing($result->media->path);
    }

    public function test_same_source_url_returns_existing_without_fetching_or_overwriting_metadata(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $importer = $this->importer($fetcher);
        $created = $importer->import(new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: 'Admin-owned title',
            alt: 'Admin-owned alt',
            handle: 'home-technician',
        ));

        $existing = $importer->import(new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: 'Replacement title',
            alt: 'Replacement alt',
            handle: 'replacement-handle',
        ));

        $this->assertSame('existing', $existing->status);
        $this->assertTrue($created->media->is($existing->media));
        $this->assertSame(1, $fetcher->fetchCount);
        $this->assertSame('Admin-owned title', $existing->media->title);
        $this->assertSame('Admin-owned alt', $existing->media->alt);
        $this->assertSame('home-technician', $existing->media->handle);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_same_source_url_restores_a_missing_public_file_without_overwriting_admin_metadata(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fixture = base_path('public/images/home/technician-maintenance.jpg');
        $fetcher = new FakeRemoteMediaFetcher([$sourceUrl => $fixture]);
        $importer = $this->importer($fetcher);
        $created = $importer->import(new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: 'Admin-owned title',
            alt: 'Admin-owned alt',
            handle: 'home-technician',
        ));
        $path = $created->media->path;
        Storage::disk('public')->delete($path);

        $restored = $importer->import(new MediaImportItem(
            sourceUrl: $sourceUrl,
            title: 'Replacement title',
            alt: 'Replacement alt',
            handle: 'replacement-handle',
        ));

        $this->assertSame('existing', $restored->status);
        $this->assertSame('The missing stored file was restored.', $restored->message);
        $this->assertTrue($created->media->is($restored->media));
        $this->assertSame(2, $fetcher->fetchCount);
        $this->assertSame($path, $restored->media->path);
        $this->assertSame('Admin-owned title', $restored->media->title);
        $this->assertSame('Admin-owned alt', $restored->media->alt);
        $this->assertSame('home-technician', $restored->media->handle);
        $this->assertSame(hash_file('sha256', $fixture), $restored->media->checksum);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_explicit_restore_refetches_an_imported_record_even_when_the_current_disk_reports_it_exists(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fixture = base_path('public/images/home/technician-maintenance.jpg');
        $fetcher = new FakeRemoteMediaFetcher([$sourceUrl => $fixture]);
        $importer = $this->importer($fetcher);
        $created = $importer->import(new MediaImportItem($sourceUrl));

        $restored = $importer->restore($created->media);

        $this->assertSame('existing', $restored->status);
        $this->assertSame('The missing stored file was restored.', $restored->message);
        $this->assertTrue($created->media->is($restored->media));
        $this->assertSame(2, $fetcher->fetchCount);
        Storage::disk('public')->assertExists($created->media->path);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_missing_imported_image_accepts_upstream_image_format_drift_and_updates_its_internal_path(): void
    {
        $sourceUrl = 'https://media.example/negotiated-image';
        $oldPath = 'media/imported/2026/07/negotiated.webp';
        $media = Media::query()->create([
            'path' => $oldPath,
            'original_name' => 'negotiated.webp',
            'mime_type' => 'image/webp',
            'size' => 123,
            'width' => 100,
            'height' => 100,
            'kind' => MediaKind::Image,
            'source_url' => $sourceUrl,
            'source_hash' => hash('sha256', $sourceUrl),
        ]);
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('existing', $result->status);
        $this->assertSame('image/jpeg', $result->media->mime_type);
        $this->assertSame(MediaKind::Image, $result->media->kind);
        $this->assertSame('media/imported/2026/07/negotiated.jpg', $result->media->path);
        Storage::disk('public')->assertExists($result->media->path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_missing_imported_record_rejects_upstream_media_kind_drift(): void
    {
        $sourceUrl = 'https://media.example/changed-kind';
        $media = Media::query()->create([
            'path' => 'media/imported/2026/07/changed-kind.mp4',
            'original_name' => 'changed-kind.mp4',
            'mime_type' => 'video/mp4',
            'size' => 123,
            'kind' => MediaKind::Video,
            'source_url' => $sourceUrl,
            'source_hash' => hash('sha256', $sourceUrl),
        ]);
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('source_kind_changed', $result->errorCode);
        $this->assertSame('video/mp4', $media->fresh()->mime_type);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_different_urls_with_the_same_checksum_create_separate_catalog_records(): void
    {
        $firstUrl = 'https://media.example/one.jpg';
        $secondUrl = 'https://cdn.example/two.jpg';
        $fixture = base_path('public/images/home/technician-maintenance.jpg');
        $fetcher = new FakeRemoteMediaFetcher([
            $firstUrl => $fixture,
            $secondUrl => $fixture,
        ]);
        $importer = $this->importer($fetcher);

        $first = $importer->import(new MediaImportItem($firstUrl));
        $second = $importer->import(new MediaImportItem($secondUrl));

        $this->assertSame('created', $first->status);
        $this->assertSame('created', $second->status);
        $this->assertNotSame($first->media->id, $second->media->id);
        $this->assertNotSame($first->media->path, $second->media->path);
        $this->assertSame($first->media->checksum, $second->media->checksum);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_unsupported_mime_returns_failed_without_a_public_file_or_row(): void
    {
        $sourceUrl = 'https://media.example/readme.txt';
        $fetcher = new FakeRemoteMediaFetcher([$sourceUrl => $this->fixture('plain text')]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('unsupported_mime', $result->errorCode);
        $this->assertNull($result->media);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertFalse(file_exists($fetcher->lastTemporaryPath()));
    }

    public function test_oversize_file_returns_failed_without_a_public_file_or_row(): void
    {
        config()->set('media-import.max_bytes', 100);
        $sourceUrl = 'https://media.example/large.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('too_large', $result->errorCode);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertFalse(file_exists($fetcher->lastTemporaryPath()));
    }

    public function test_requested_duplicate_handle_returns_handle_conflict_without_fetching(): void
    {
        $fixture = base_path('public/images/home/technician-maintenance.jpg');
        $firstUrl = 'https://media.example/one.jpg';
        $secondUrl = 'https://media.example/two.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $firstUrl => $fixture,
            $secondUrl => $fixture,
        ]);
        $importer = $this->importer($fetcher);
        $importer->import(new MediaImportItem($firstUrl, handle: 'shared-handle'));

        $result = $importer->import(new MediaImportItem($secondUrl, handle: 'shared-handle'));

        $this->assertSame('failed', $result->status);
        $this->assertSame('handle_conflict', $result->errorCode);
        $this->assertSame(1, $fetcher->fetchCount);
        $this->assertDatabaseCount('media', 1);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_creates_no_catalog_row(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);

        $result = $this->importer($fetcher, $disk)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('storage_failed', $result->errorCode);
        $this->assertDatabaseCount('media', 0);
        $this->assertFalse(file_exists($fetcher->lastTemporaryPath()));
    }

    public function test_storage_failure_removes_a_partially_materialized_public_file(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnUsing(
            function (string $path, mixed $contents): bool {
                Storage::disk('public')->put($path, $contents);

                return false;
            },
        );
        $disk->shouldReceive('delete')->once()->andReturnUsing(
            fn (string $path): bool => Storage::disk('public')->delete($path),
        );

        $result = $this->importer($fetcher, $disk)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('storage_failed', $result->errorCode);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 0);
    }

    public function test_cleanup_refusal_is_logged_and_returned_as_cleanup_failed(): void
    {
        Log::spy();
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnUsing(
            function (string $path, mixed $contents): bool {
                Storage::disk('public')->put($path, $contents);

                return false;
            },
        );
        $disk->shouldReceive('delete')->once()->andReturn(false);

        $result = $this->importer($fetcher, $disk)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('cleanup_failed', $result->errorCode);
        $this->assertSame('The failed import may have left an incomplete stored file.', $result->message);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Remote media cleanup failed.', Mockery::on(
                fn (array $context): bool => isset($context['path'], $context['reason']),
            ));
    }

    public function test_unexpected_storage_exception_is_surfaced_after_partial_file_cleanup(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnUsing(
            function (string $path, mixed $contents): never {
                Storage::disk('public')->put($path, $contents);

                throw new RuntimeException('Unexpected adapter failure.');
            },
        );
        $disk->shouldReceive('delete')->once()->andReturnUsing(
            fn (string $path): bool => Storage::disk('public')->delete($path),
        );

        try {
            $this->importer($fetcher, $disk)->import(new MediaImportItem($sourceUrl));
            $this->fail('Expected the unexpected storage exception to surface.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unexpected adapter failure.', $exception->getMessage());
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('media', 0);
        }
    }

    public function test_database_failure_removes_the_newly_stored_public_file(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);
        Media::creating(function (): void {
            throw new QueryException(
                'testing',
                'insert into media',
                [],
                new PDOException('Forced database failure.'),
            );
        });

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('database_failed', $result->errorCode);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertFalse(file_exists($fetcher->lastTemporaryPath()));
    }

    public function test_concurrent_source_hash_winner_is_returned_as_existing_and_losing_file_is_deleted(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $winnerPath = 'media/imported/winner.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);

        Media::creating(function (Media $loser) use ($sourceUrl, $winnerPath): void {
            Storage::disk('public')->put($winnerPath, 'winner bytes');
            DB::table('media')->insert([
                'path' => $winnerPath,
                'original_name' => 'winner.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 12,
                'kind' => 'image',
                'source_url' => $sourceUrl,
                'source_hash' => $loser->source_hash,
                'checksum' => hash('sha256', 'winner bytes'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('existing', $result->status);
        $this->assertSame($winnerPath, $result->media->path);
        $this->assertSame([$winnerPath], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 1);
    }

    public function test_concurrent_handle_winner_returns_handle_conflict_and_losing_file_is_deleted(): void
    {
        $sourceUrl = 'https://media.example/technician.jpg';
        $winnerPath = 'media/imported/handle-winner.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => base_path('public/images/home/technician-maintenance.jpg'),
        ]);

        Media::creating(function (Media $loser) use ($winnerPath): void {
            Storage::disk('public')->put($winnerPath, 'winner bytes');
            DB::table('media')->insert([
                'path' => $winnerPath,
                'original_name' => 'handle-winner.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 12,
                'kind' => 'image',
                'handle' => $loser->handle,
                'source_url' => 'https://media.example/handle-winner.jpg',
                'source_hash' => hash('sha256', 'https://media.example/handle-winner.jpg'),
                'checksum' => hash('sha256', 'winner bytes'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $this->importer($fetcher)->import(new MediaImportItem(
            sourceUrl: $sourceUrl,
            handle: 'concurrent-handle',
        ));

        $this->assertSame('failed', $result->status);
        $this->assertSame('handle_conflict', $result->errorCode);
        $this->assertSame([$winnerPath], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 1);
    }

    public function test_mp4_is_cataloged_as_video_with_null_dimensions(): void
    {
        $sourceUrl = 'https://media.example/hero.mp4';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => $this->fixture("\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41"),
        ]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('created', $result->status);
        $this->assertSame(MediaKind::Video, $result->media->kind);
        $this->assertSame('video/mp4', $result->media->mime_type);
        $this->assertNull($result->media->width);
        $this->assertNull($result->media->height);
        $this->assertStringEndsWith('.mp4', $result->media->path);
        Storage::disk('public')->assertExists($result->media->path);
    }

    public function test_truncated_image_bytes_are_rejected_even_when_mime_is_image(): void
    {
        $sourceUrl = 'https://media.example/truncated.jpg';
        $fetcher = new FakeRemoteMediaFetcher([
            $sourceUrl => $this->fixture("\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01truncated"),
        ]);

        $result = $this->importer($fetcher)->import(new MediaImportItem($sourceUrl));

        $this->assertSame('failed', $result->status);
        $this->assertSame('invalid_image', $result->errorCode);
        $this->assertNull($result->media);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function importer(
        RemoteMediaFetcher $fetcher,
        ?Filesystem $disk = null,
    ): RemoteMediaImporter {
        return new RemoteMediaImporter($fetcher, $disk);
    }

    private function fixture(string $contents): string
    {
        $directory = storage_path('framework/testing/media-import-fixtures');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'fixture-');
        file_put_contents($path, $contents);
        $this->fixtureFiles[] = $path;

        return $path;
    }
}

class FakeRemoteMediaFetcher extends RemoteMediaFetcher
{
    public int $fetchCount = 0;

    /** @var array<int, string> */
    private array $temporaryPaths = [];

    /**
     * @param  array<string, string>  $fixtures
     */
    public function __construct(private readonly array $fixtures) {}

    public function fetch(string $url): SplFileInfo
    {
        $this->fetchCount++;

        $directory = storage_path('framework/testing/media-import-downloads');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'download-');
        copy($this->fixtures[$url], $path);
        $this->temporaryPaths[] = $path;

        return new SplFileInfo($path);
    }

    public function lastTemporaryPath(): string
    {
        return $this->temporaryPaths[array_key_last($this->temporaryPaths)] ?? '';
    }
}
