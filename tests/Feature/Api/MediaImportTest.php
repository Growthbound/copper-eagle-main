<?php

namespace Tests\Feature\Api;

use App\Enums\MediaKind;
use App\Models\ApiIdempotencyRecord;
use App\Models\Media;
use App\Models\User;
use App\Services\ApiKeyManager;
use App\Services\Media\RemoteMediaCleanupFailed;
use App\Services\Media\RemoteMediaImporter;
use App\ValueObjects\MediaImportItem;
use App\ValueObjects\MediaImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class MediaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_required_before_every_payload_shape_is_considered(): void
    {
        $payloads = [
            ['items' => []],
            ['items' => array_fill(0, 26, ['source_url' => 'https://cdn.example.com/photo.jpg'])],
            ['items' => [['source_url' => 'not-a-url']]],
            ['items' => [
                ['source_url' => 'https://cdn.example.com/same.jpg'],
                ['source_url' => 'https://cdn.example.com/same.jpg'],
            ]],
            ['items' => [['source_url' => 'https://cdn.example.com/photo.jpg', 'handle' => 'Bad Handle']]],
            ['items' => [['source_url' => 'https://cdn.example.com/photo.jpg']]],
        ];

        foreach ($payloads as $payload) {
            $this->postJson('/api/media/import', $payload)
                ->assertUnauthorized()
                ->assertHeader('X-Request-ID')
                ->assertJsonPath('message', 'Invalid API credentials.')
                ->assertJsonPath('code', 'unauthenticated')
                ->assertJsonStructure(['request_id']);
        }
    }

    public function test_items_must_not_be_empty(): void
    {
        $this->authenticate()
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_a_batch_cannot_contain_more_than_twenty_five_items(): void
    {
        $items = [];

        for ($index = 1; $index <= 26; $index++) {
            $items[] = ['source_url' => "https://cdn.example.com/photo-{$index}.jpg"];
        }

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => $items])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_each_source_url_must_be_an_http_or_https_url(): void
    {
        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [['source_url' => 'ftp://cdn.example.com/photo.jpg']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.source_url']);
    }

    public function test_source_urls_are_bounded_before_external_import_side_effects_run(): void
    {
        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('import');
        });

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [[
                'source_url' => 'https://cdn.example.com/'.str_repeat('a', 2048),
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.source_url']);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_source_urls_must_be_distinct_within_a_batch(): void
    {
        $sourceUrl = 'https://cdn.example.com/same.jpg';

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [
                ['source_url' => $sourceUrl],
                ['source_url' => $sourceUrl],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.source_url', 'items.1.source_url']);
    }

    public function test_handles_must_use_lowercase_kebab_case(): void
    {
        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [[
                'source_url' => 'https://cdn.example.com/photo.jpg',
                'handle' => 'Hero_Image',
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.handle']);
    }

    public function test_a_valid_batch_returns_an_honest_result_ledger_and_normalizes_optional_strings(): void
    {
        URL::forceRootUrl('https://www.example.test');
        URL::forceScheme('https');

        $createdMedia = $this->media(41, 'media/imported/2026/07/hero.jpg', 'hero-image');
        $existingMedia = $this->media(42, 'media/imported/2026/07/team.jpg');

        $importer = $this->mock(RemoteMediaImporter::class, function (MockInterface $mock) use ($createdMedia, $existingMedia): void {
            $mock->shouldReceive('import')
                ->once()
                ->ordered()
                ->withArgs(fn (MediaImportItem $item): bool => $item->sourceUrl === 'https://cdn.example.com/hero.jpg'
                    && $item->title === 'Hero photo'
                    && $item->alt === null
                    && $item->handle === 'hero-image')
                ->andReturn(new MediaImportResult(
                    status: 'created',
                    sourceUrl: 'https://cdn.example.com/hero.jpg',
                    media: $createdMedia,
                ));

            $mock->shouldReceive('import')
                ->once()
                ->ordered()
                ->withArgs(fn (MediaImportItem $item): bool => $item->sourceUrl === 'https://cdn.example.com/team.jpg'
                    && $item->title === null
                    && $item->alt === 'Team at work'
                    && $item->handle === null)
                ->andReturn(new MediaImportResult(
                    status: 'existing',
                    sourceUrl: 'https://cdn.example.com/team.jpg',
                    media: $existingMedia,
                ));
        });

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [
                [
                    'source_url' => 'https://cdn.example.com/hero.jpg',
                    'title' => '  Hero photo  ',
                    'alt' => '   ',
                    'handle' => ' hero-image ',
                ],
                [
                    'source_url' => 'https://cdn.example.com/team.jpg',
                    'title' => '',
                    'alt' => '  Team at work ',
                    'handle' => null,
                ],
            ]])
            ->assertOk()
            ->assertExactJson([
                'summary' => [
                    'requested' => 2,
                    'created' => 1,
                    'existing' => 1,
                    'failed' => 0,
                ],
                'items' => [
                    [
                        'status' => 'created',
                        'source_url' => 'https://cdn.example.com/hero.jpg',
                        'media' => [
                            'id' => 41,
                            'url' => 'https://www.example.test/media/41',
                            'kind' => 'image',
                            'mime_type' => 'image/jpeg',
                            'original_name' => 'hero.jpg',
                            'size' => 1024,
                            'width' => 1200,
                            'height' => 800,
                            'title' => 'Imported image',
                            'alt' => 'Imported image',
                            'handle' => 'hero-image',
                        ],
                        'error_code' => null,
                        'message' => null,
                    ],
                    [
                        'status' => 'existing',
                        'source_url' => 'https://cdn.example.com/team.jpg',
                        'media' => [
                            'id' => 42,
                            'url' => 'https://www.example.test/media/42',
                            'kind' => 'image',
                            'mime_type' => 'image/jpeg',
                            'original_name' => 'team.jpg',
                            'size' => 1024,
                            'width' => 1200,
                            'height' => 800,
                            'title' => 'Imported image',
                            'alt' => 'Imported image',
                            'handle' => null,
                        ],
                        'error_code' => null,
                        'message' => null,
                    ],
                ],
            ]);
    }

    public function test_a_failed_item_does_not_hide_a_later_success(): void
    {
        $createdMedia = $this->media(51, 'media/imported/2026/07/second.jpg');

        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock) use ($createdMedia): void {
            $mock->shouldReceive('import')
                ->once()
                ->ordered()
                ->andReturn(new MediaImportResult(
                    status: 'failed',
                    sourceUrl: 'https://cdn.example.com/first.txt',
                    errorCode: 'unsupported_mime',
                    message: 'The remote file type is not supported.',
                ));

            $mock->shouldReceive('import')
                ->once()
                ->ordered()
                ->andReturn(new MediaImportResult(
                    status: 'created',
                    sourceUrl: 'https://cdn.example.com/second.jpg',
                    media: $createdMedia,
                ));
        });

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [
                ['source_url' => 'https://cdn.example.com/first.txt'],
                ['source_url' => 'https://cdn.example.com/second.jpg'],
            ]])
            ->assertOk()
            ->assertJsonPath('summary.requested', 2)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.existing', 0)
            ->assertJsonPath('summary.failed', 1)
            ->assertJsonPath('items.0.status', 'failed')
            ->assertJsonPath('items.0.error_code', 'unsupported_mime')
            ->assertJsonPath('items.1.status', 'created');
    }

    public function test_an_expected_cleanup_exception_is_recorded_for_its_item(): void
    {
        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock): void {
            $mock->shouldReceive('import')
                ->once()
                ->andThrow(new RemoteMediaCleanupFailed);
        });

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [[
                'source_url' => 'https://cdn.example.com/photo.jpg',
            ]]])
            ->assertOk()
            ->assertJsonPath('summary.failed', 1)
            ->assertJsonPath('items.0.status', 'failed')
            ->assertJsonPath('items.0.error_code', 'cleanup_failed')
            ->assertJsonPath('items.0.message', 'The failed import may have left an incomplete stored file.');
    }

    public function test_successful_import_retries_replay_the_original_ledger(): void
    {
        $media = $this->media(61, 'media/imported/2026/07/replay.jpg');
        $transactionLevel = DB::transactionLevel();
        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock) use ($media, $transactionLevel): void {
            $mock->shouldReceive('import')
                ->once()
                ->andReturnUsing(function () use ($media, $transactionLevel): MediaImportResult {
                    $this->assertSame($transactionLevel, DB::transactionLevel());

                    return new MediaImportResult(
                        status: 'created',
                        sourceUrl: 'https://cdn.example.com/replay.jpg',
                        media: $media,
                    );
                });
        });
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Replay importer');
        $payload = ['items' => [[
            'source_url' => 'https://cdn.example.com/replay.jpg',
        ]]];
        $client = $this->withHeader('X-Request-ID', 'media-import-correlation-001')
            ->withBasicAuth($owner->email, $issued->plaintext)
            ->withHeader('Idempotency-Key', 'media-import-replay-20260726');

        $first = $client->postJson('/api/media/import', $payload)
            ->assertOk()
            ->assertHeader('X-Request-ID', 'media-import-correlation-001')
            ->assertHeader('Idempotency-Replayed', 'false');
        $second = $client->postJson('/api/media/import', $payload)
            ->assertOk()
            ->assertHeader('X-Request-ID', 'media-import-correlation-001')
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('api_idempotency_records', 1);
        $this->assertSame(
            'media-import-correlation-001',
            ApiIdempotencyRecord::query()->sole()->response_headers['X-Request-ID'] ?? null,
        );
    }

    public function test_unexpected_importer_exceptions_are_not_converted_into_item_failures(): void
    {
        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock): void {
            $mock->shouldReceive('import')
                ->once()
                ->andThrow(new RuntimeException('programming error'));
        });

        $this->withoutExceptionHandling();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('programming error');

        $this->authenticate()
            ->postJson('/api/media/import', ['items' => [[
                'source_url' => 'https://cdn.example.com/photo.jpg',
            ]]]);
    }

    public function test_a_later_batch_exception_does_not_roll_back_an_earlier_catalog_write(): void
    {
        $call = 0;
        $this->mock(RemoteMediaImporter::class, function (MockInterface $mock) use (&$call): void {
            $mock->shouldReceive('import')
                ->twice()
                ->andReturnUsing(function (MediaImportItem $item) use (&$call): MediaImportResult {
                    $call++;

                    if ($call === 2) {
                        throw new RuntimeException('later item failed');
                    }

                    $media = Media::query()->create([
                        'path' => 'media/imported/2026/07/first-survives.jpg',
                        'original_name' => 'first-survives.jpg',
                        'mime_type' => 'image/jpeg',
                        'size' => 1024,
                        'width' => 1200,
                        'height' => 800,
                        'alt' => 'First imported item',
                        'kind' => MediaKind::Image,
                        'source_url' => $item->sourceUrl,
                        'source_hash' => hash('sha256', $item->sourceUrl),
                        'checksum' => str_repeat('a', 64),
                    ]);

                    return new MediaImportResult(
                        status: 'created',
                        sourceUrl: $item->sourceUrl,
                        media: $media,
                    );
                });
        });
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Failure importer');

        $this->withHeader('X-Request-ID', 'media-import-failure-001')
            ->withBasicAuth($owner->email, $issued->plaintext)
            ->withHeader('Idempotency-Key', 'media-import-partial-failure-001')
            ->postJson('/api/media/import', ['items' => [
                ['source_url' => 'https://cdn.example.com/first.jpg'],
                ['source_url' => 'https://cdn.example.com/second.jpg'],
            ]])
            ->assertStatus(500)
            ->assertHeader('X-Request-ID', 'media-import-failure-001')
            ->assertJsonPath('code', 'request_failed')
            ->assertJsonPath('request_id', 'media-import-failure-001');

        $this->assertDatabaseHas('media', [
            'source_url' => 'https://cdn.example.com/first.jpg',
        ]);
        $this->assertDatabaseCount('api_idempotency_records', 0);
    }

    private function authenticate(): static
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Test importer');

        return $this->withBasicAuth($owner->email, $issued->plaintext)
            ->withHeader('Idempotency-Key', 'media-import-test-'.str()->uuid());
    }

    private function media(int $id, string $path, ?string $handle = null): Media
    {
        $media = new Media([
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1200,
            'height' => 800,
            'kind' => MediaKind::Image,
            'title' => 'Imported image',
            'alt' => 'Imported image',
            'handle' => $handle,
        ]);

        $media->setAttribute('id', $id);

        return $media;
    }
}
