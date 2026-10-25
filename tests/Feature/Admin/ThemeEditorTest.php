<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ThemeEditor;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThemeEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_a_saved_color_reaches_the_public_site(): void
    {
        Livewire::test(ThemeEditor::class)
            ->set('colors.teal', '#123456')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#123456', Theme::palette()['teal']);

        $this->get('/')->assertSee('--color-teal:#123456', false);
    }

    public function test_the_stock_palette_adds_no_override_markup(): void
    {
        $this->assertSame('', Theme::cssOverrides());

        $this->get('/')->assertDontSee('--color-teal:', false);
    }

    public function test_a_malformed_color_is_rejected(): void
    {
        Livewire::test(ThemeEditor::class)
            ->set('colors.teal', 'dark green')
            ->call('save')
            ->assertHasErrors('colors.teal');

        $this->assertSame(config('theme.colors.teal.default'), Theme::palette()['teal']);
    }

    public function test_resetting_restores_every_default(): void
    {
        Theme::save(['teal' => '#123456', 'orange' => '#654321']);

        Livewire::test(ThemeEditor::class)->call('resetToDefaults');

        $this->assertSame(Theme::defaults(), array_intersect_key(Theme::palette(), Theme::defaults()));
        $this->assertSame('', Theme::cssOverrides());
    }

    public function test_the_editor_scores_contrast_so_unreadable_pairs_are_visible(): void
    {
        Livewire::test(ThemeEditor::class)
            ->set('colors.ink', '#fefefe')
            ->assertSee('below the 4.5:1 minimum');
    }
}
