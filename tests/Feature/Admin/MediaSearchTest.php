<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\MediaLibrary;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MediaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_images_can_be_found_by_file_name(): void
    {
        $this->makeMedia('duct-before.jpg');
        $this->makeMedia('team-photo.jpg');

        Livewire::test(MediaLibrary::class)
            ->set('search', 'duct')
            ->assertSee('duct-before.jpg')
            ->assertDontSee('team-photo.jpg');
    }

    public function test_images_can_be_found_by_description(): void
    {
        $this->makeMedia('IMG_4821.jpg', 'Return duct before cleaning');
        $this->makeMedia('IMG_4822.jpg', 'Crew van exterior');

        Livewire::test(MediaLibrary::class)
            ->set('search', 'cleaning')
            ->assertSee('IMG_4821.jpg')
            ->assertDontSee('IMG_4822.jpg');
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->makeMedia('Duct-Before.jpg');

        Livewire::test(MediaLibrary::class)
            ->set('search', 'DUCT')
            ->assertSee('Duct-Before.jpg');
    }

    public function test_a_search_with_no_matches_explains_what_is_searched(): void
    {
        $this->makeMedia('duct-before.jpg');

        Livewire::test(MediaLibrary::class)
            ->set('search', 'nothing-matches-this')
            ->assertSee('No media matches')
            ->assertSee('file names, titles, descriptions, and handles');
    }

    public function test_an_empty_library_prompts_an_upload_rather_than_a_search(): void
    {
        Livewire::test(MediaLibrary::class)
            ->assertSee('No media yet')
            ->assertDontSee('No media matches');
    }

    public function test_search_and_the_kind_filter_narrow_together(): void
    {
        $this->makeMedia('duct-walkthrough.mp4', 'Duct walkthrough', 'video');
        $this->makeMedia('duct-before.jpg', 'Duct before cleaning');

        Livewire::test(MediaLibrary::class)
            ->set('search', 'duct')
            ->assertSee('duct-walkthrough.mp4')
            ->assertSee('duct-before.jpg')
            ->set('kindFilter', 'video')
            ->assertSee('duct-walkthrough.mp4')
            ->assertDontSee('duct-before.jpg');
    }

    public function test_a_filtered_view_with_no_search_says_so(): void
    {
        $this->makeMedia('duct-before.jpg');

        Livewire::test(MediaLibrary::class)
            ->set('kindFilter', 'video')
            ->assertSee('No media matches this filter')
            ->assertDontSee('No media yet');
    }

    public function test_searching_returns_to_the_first_page_of_results(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeMedia('photo-'.$i.'.jpg');
        }

        Livewire::test(MediaLibrary::class)
            ->call('setPage', 2)
            ->set('search', 'photo-1')
            ->assertSet('paginators.page', 1);
    }

    private function makeMedia(string $name, ?string $alt = null, string $kind = 'image'): Media
    {
        return Media::query()->create([
            'path' => 'media/'.bin2hex(random_bytes(8)).'.'.pathinfo($name, PATHINFO_EXTENSION),
            'original_name' => $name,
            'mime_type' => $kind === 'video' ? 'video/mp4' : 'image/jpeg',
            'size' => 1024,
            'alt' => $alt,
            'kind' => $kind,
        ]);
    }
}
