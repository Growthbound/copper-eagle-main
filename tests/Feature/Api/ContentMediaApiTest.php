<?php

namespace Tests\Feature\Api;

use App\Content\Enums\ApiKeyScope;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithContentApi;
use Tests\TestCase;

class ContentMediaApiTest extends TestCase
{
    use InteractsWithContentApi;
    use RefreshDatabase;

    public function test_media_search_requires_media_read_scope(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentRead]);

        $this->contentGet('/api/content/v1/media')
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'media:read');
    }

    public function test_media_search_is_bounded_useful_and_does_not_expose_storage_internals(): void
    {
        $this->authenticateContentApi([ApiKeyScope::MediaRead]);
        Media::create([
            'path' => 'media/private-random-name.jpg',
            'original_name' => 'inspection-camera.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'width' => 1200,
            'height' => 800,
            'title' => 'Inspection camera',
            'alt' => 'Team member at work',
            'handle' => 'inspection-camera',
            'source_url' => 'https://private.example.test/original.jpg',
            'checksum' => str_repeat('a', 64),
        ]);
        Media::create([
            'path' => 'media/team.jpg',
            'original_name' => 'team.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'title' => 'Example Co. team',
        ]);

        $response = $this->contentGet(
            '/api/content/v1/media?search=inspection&kind=image&per_page=999',
        )->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('data.0.handle', 'inspection-camera')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'url',
                    'kind',
                    'mime_type',
                    'original_name',
                    'size',
                    'width',
                    'height',
                    'title',
                    'alt',
                    'handle',
                ]],
            ]);

        $this->assertStringNotContainsString('private-random-name', $response->getContent());
        $this->assertStringNotContainsString('private.example.test', $response->getContent());
        $this->assertStringNotContainsString(str_repeat('a', 64), $response->getContent());
    }
}
