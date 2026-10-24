<?php

namespace Tests\Feature\Admin;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Enums\MediaKind;
use App\Livewire\Admin\MediaLibrary;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::factory()->create());
    }

    public function test_an_uploaded_image_is_stored_and_catalogued(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->image('project-before.jpg', 800, 600)->size(25_000)])
            ->assertHasNoErrors();

        $media = Media::query()->sole();

        $this->assertSame('project-before.jpg', $media->original_name);
        $this->assertSame(MediaKind::Image, $media->kind);
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);

        Storage::disk('public')->assertExists($media->path);
    }

    public function test_a_valid_mp4_is_stored_and_catalogued_as_video(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [$this->mp4('hero.mp4')])
            ->assertHasNoErrors();

        $media = Media::query()->sole();

        $this->assertSame('hero.mp4', $media->original_name);
        $this->assertSame(MediaKind::Video, $media->kind);
        $this->assertSame('video/mp4', $media->mime_type);
        $this->assertNull($media->width);
        $this->assertNull($media->height);

        Storage::disk('public')->assertExists($media->path);
    }

    public function test_valid_image_bytes_named_with_an_mp4_extension_are_rejected_not_misclassified_as_video(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->image('spoofed.mp4', 800, 600)])
            ->assertHasErrors('uploads.*');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_valid_mp4_bytes_named_with_an_image_extension_are_rejected(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [$this->mp4('spoofed.jpg')])
            ->assertHasErrors('uploads.*');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_unsupported_video_and_document_uploads_are_rejected_without_side_effects(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->create('clip.mov', 100, 'video/quicktime')])
            ->assertHasErrors('uploads.*');

        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')])
            ->assertHasErrors('uploads.*');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_upload_over_twenty_five_megabytes_is_rejected(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->image('huge.jpg')->size(25_601)])
            ->assertHasErrors('uploads.*');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_kind_filter_is_applied_before_results_are_rendered(): void
    {
        $image = $this->media(['original_name' => 'sample-image.jpg']);
        $video = $this->media([
            'kind' => MediaKind::Video,
            'path' => 'media/sample-video.mp4',
            'original_name' => 'sample-video.mp4',
            'mime_type' => 'video/mp4',
            'width' => null,
            'height' => null,
        ]);

        Livewire::test(MediaLibrary::class)
            ->set('kindFilter', 'image')
            ->assertSee($image->original_name)
            ->assertDontSee($video->original_name)
            ->set('kindFilter', 'video')
            ->assertSee($video->original_name)
            ->assertDontSee($image->original_name);
    }

    public function test_changing_the_kind_filter_resets_pagination(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('paginators.page', 3)
            ->set('kindFilter', 'video')
            ->assertSet('paginators.page', 1);
    }

    public function test_image_and_video_records_render_the_correct_preview_markup(): void
    {
        $image = $this->media(['original_name' => 'sample-image.jpg']);
        $video = $this->media([
            'kind' => MediaKind::Video,
            'path' => 'media/sample-video.mp4',
            'original_name' => 'sample-video.mp4',
            'mime_type' => 'video/mp4',
            'width' => null,
            'height' => null,
        ]);

        Livewire::test(MediaLibrary::class)
            ->assertSeeHtml('<img')
            ->assertSeeHtml('src="'.$image->url().'"')
            ->assertSeeHtml('<video controls preload="metadata"')
            ->assertSeeHtml('<source src="'.$video->url().'" type="video/mp4"');
    }

    public function test_imported_source_metadata_is_rendered(): void
    {
        $media = $this->media([
            'title' => 'Homepage team photo',
            'handle' => 'home-team-photo',
            'source_url' => 'https://cdn.example.com/uploads/team-photo.jpg',
            'size' => 2_048,
            'width' => 1200,
            'height' => 800,
        ]);

        Livewire::test(MediaLibrary::class)
            ->assertSee($media->title)
            ->assertSee($media->handle)
            ->assertSee($media->source_url)
            ->assertSee('Image')
            ->assertSee('2 KB')
            ->assertSee('1200×800');
    }

    public function test_imported_source_metadata_does_not_render_url_credentials_or_query_secrets(): void
    {
        $this->media([
            'source_url' => 'https://source-user:source-password@cdn.example.com/uploads/team-photo.jpg?api_key=secret#private',
        ]);

        Livewire::test(MediaLibrary::class)
            ->assertSee('https://cdn.example.com/uploads/team-photo.jpg')
            ->assertDontSee('source-user')
            ->assertDontSee('source-password')
            ->assertDontSee('api_key')
            ->assertDontSee('secret');
    }

    public function test_title_alt_and_handle_can_be_saved_and_blank_values_become_null(): void
    {
        $media = $this->media();

        Livewire::test(MediaLibrary::class)
            ->set('title.'.$media->id, ' Homepage team photo ')
            ->set('alt.'.$media->id, ' Team member greeting a customer ')
            ->set('handle.'.$media->id, ' home-team-photo ')
            ->call('saveDetails', $media->id)
            ->assertHasNoErrors();

        $media->refresh();
        $this->assertSame('Homepage team photo', $media->title);
        $this->assertSame('Team member greeting a customer', $media->alt);
        $this->assertSame('home-team-photo', $media->handle);

        Livewire::test(MediaLibrary::class)
            ->set('title.'.$media->id, '   ')
            ->set('alt.'.$media->id, '')
            ->set('handle.'.$media->id, ' ')
            ->call('saveDetails', $media->id)
            ->assertHasNoErrors();

        $media->refresh();
        $this->assertNull($media->title);
        $this->assertNull($media->alt);
        $this->assertNull($media->handle);
    }

    public function test_alt_text_cannot_be_cleared_while_managed_content_depends_on_it(): void
    {
        $media = $this->media([
            'alt' => 'Team member preparing for a visit',
        ]);
        $entry = app(ContentLifecycle::class)->createDraft([
            'kind' => ContentKind::Page,
            'slug' => 'about',
            'title' => 'About Example Co.',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'About Example Co.',
            'blocks' => [[
                'type' => 'content/split',
                'props' => [
                    'heading' => 'A careful process',
                    'body' => 'See how the work is prepared.',
                    'image' => ['media_id' => $media->id],
                ],
            ]],
        ], auth()->user());
        app(ContentLifecycle::class)->publish(
            $entry,
            $entry->revisions()->sole(),
            0,
            auth()->user(),
        );

        Livewire::test(MediaLibrary::class)
            ->set('alt.'.$media->id, '')
            ->call('saveDetails', $media->id)
            ->assertHasErrors('alt.'.$media->id)
            ->assertSee('Alt text cannot be cleared');

        $this->assertSame(
            'Team member preparing for a visit',
            $media->refresh()->alt,
        );
    }

    public function test_invalid_and_duplicate_handles_do_not_overwrite_the_record(): void
    {
        $existing = $this->media([
            'path' => 'media/existing.jpg',
            'original_name' => 'existing.jpg',
            'handle' => 'shared-handle',
        ]);
        $media = $this->media(['handle' => 'original-handle']);

        Livewire::test(MediaLibrary::class)
            ->set('handle.'.$media->id, 'Invalid Handle')
            ->call('saveDetails', $media->id)
            ->assertHasErrors('handle.'.$media->id);

        $this->assertSame('original-handle', $media->refresh()->handle);

        Livewire::test(MediaLibrary::class)
            ->set('handle.'.$media->id, $existing->handle)
            ->call('saveDetails', $media->id)
            ->assertHasErrors('handle.'.$media->id);

        $this->assertSame('original-handle', $media->refresh()->handle);
    }

    public function test_saving_an_unchanged_handle_ignores_the_current_record(): void
    {
        $media = $this->media(['handle' => 'home-team-photo']);

        Livewire::test(MediaLibrary::class)
            ->set('handle.'.$media->id, 'home-team-photo')
            ->call('saveDetails', $media->id)
            ->assertHasNoErrors();

        $this->assertSame('home-team-photo', $media->refresh()->handle);
    }

    public function test_deleting_an_imported_mp4_removes_the_stored_file_and_row(): void
    {
        $media = $this->media([
            'kind' => MediaKind::Video,
            'path' => 'media/imported/hero.mp4',
            'original_name' => 'hero.mp4',
            'mime_type' => 'video/mp4',
            'source_url' => 'https://cdn.example.com/uploads/hero.mp4',
            'width' => null,
            'height' => null,
        ]);
        Storage::disk('public')->put($media->path, 'video');

        Livewire::test(MediaLibrary::class)->call('delete', $media->id);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_deleting_an_uploaded_image_still_removes_the_stored_file_and_row(): void
    {
        Livewire::test(MediaLibrary::class)
            ->set('uploads', [UploadedFile::fake()->image('project-before.jpg')]);

        $media = Media::query()->sole();

        Livewire::test(MediaLibrary::class)->call('delete', $media->id);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_media_used_by_content_cannot_be_deleted_and_names_the_blocking_entry(): void
    {
        $media = $this->media();
        Storage::disk('public')->put($media->path, 'image');
        app(ContentLifecycle::class)->createDraft([
            'kind' => ContentKind::Page,
            'slug' => 'about',
            'title' => 'About Example Co.',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'About Example Co.',
            'blocks' => [[
                'type' => 'content/split',
                'props' => [
                    'heading' => 'A careful process',
                    'body' => 'See how the work is prepared.',
                    'image' => [
                        'media_id' => $media->id,
                        'alt' => 'Team member preparing for a visit',
                        'width' => 1200,
                        'height' => 800,
                    ],
                ],
            ]],
        ], auth()->user());

        Livewire::test(MediaLibrary::class)
            ->call('delete', $media->id)
            ->assertHasErrors('delete')
            ->assertSee('About Example Co.');

        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_active_brand_logo_cannot_be_deleted(): void
    {
        $media = $this->media();
        Storage::disk('public')->put($media->path, 'image');
        Setting::write(['site.logo_media_id' => (string) $media->id]);

        Livewire::test(MediaLibrary::class)
            ->call('delete', $media->id)
            ->assertHasErrors('delete')
            ->assertSee('active brand logo');

        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    private function mp4(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08mdat",
        );
    }

    /** @param array<string, mixed> $overrides */
    private function media(array $overrides = []): Media
    {
        return Media::query()->create(array_merge([
            'kind' => MediaKind::Image,
            'path' => 'media/sample-image.jpg',
            'original_name' => 'sample-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1_024,
            'width' => 800,
            'height' => 600,
        ], $overrides));
    }
}
