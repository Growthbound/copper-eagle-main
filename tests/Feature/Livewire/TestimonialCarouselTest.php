<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TestimonialCarousel;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialCarouselTest extends TestCase
{
    public function test_it_starts_on_the_first_review_and_renders_accessible_dots(): void
    {
        $component = $this->livewireComponent();
        $html = $component->html();

        $component->assertSet('active', 0);
        $this->assertSame(6, substr_count($html, 'wire:click="select('));
        $this->assertStringContainsString('aria-label="Show review from Example Customer 1"', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*wire:click="select\\(0\\)"[^>]*aria-current="true"/s',
            $html,
        );
    }

    public function test_selecting_a_valid_dot_changes_the_active_review(): void
    {
        $this->livewireComponent()
            ->call('select', 3)
            ->assertSet('active', 3)
            ->assertSee('Example Customer 4');
    }

    public function test_invalid_selection_is_ignored(): void
    {
        $this->livewireComponent()
            ->call('select', -1)
            ->assertSet('active', 0)
            ->call('select', 99)
            ->assertSet('active', 0);
    }

    public function test_a_large_collection_renders_a_unique_five_card_window(): void
    {
        $html = $this->livewireComponent()->html();

        $this->assertSame(5, substr_count($html, 'data-review-index="'));

        foreach ([0, 1, 2, 4, 5] as $index) {
            $this->assertSame(1, substr_count($html, 'data-review-index="'.$index.'"'));
        }

        $this->assertStringNotContainsString('data-review-index="3"', $html);
    }

    public function test_small_collections_render_without_duplicate_cards(): void
    {
        $html = Livewire::test(TestimonialCarousel::class, [
            'items' => array_slice($this->items(), 0, 2),
            'heading' => 'Two examples',
            'carouselId' => 'two-example-reviews',
        ])->html();

        $this->assertSame(2, substr_count($html, 'data-review-index="'));
        $this->assertSame(1, substr_count($html, 'data-review-index="0"'));
        $this->assertSame(1, substr_count($html, 'data-review-index="1"'));
    }

    public function test_video_cards_render_without_quote_only_author_fields(): void
    {
        $html = Livewire::test(TestimonialCarousel::class, [
            'items' => [
                [
                    'type' => 'video',
                    'title' => 'Video customer story',
                    'context' => 'Verified customer video',
                    'video_url' => 'https://example.com/video',
                    'poster' => null,
                ],
                $this->items()[0],
            ],
            'heading' => 'Customer stories',
            'carouselId' => 'video-customer-stories',
        ])->html();

        $this->assertStringContainsString('Video customer story', $html);
        $this->assertStringContainsString('Show video Video customer story', $html);
        $this->assertStringContainsString('Show review from Example Customer 1', $html);
    }

    private function livewireComponent()
    {
        return Livewire::test(TestimonialCarousel::class, [
            'items' => $this->items(),
            'eyebrow' => 'Synthetic preview',
            'heading' => 'What example customers say',
            'carouselId' => 'example-reviews',
        ]);
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     quote: string,
     *     author: string,
     *     context: string,
     *     avatar: null
     * }>
     */
    private function items(): array
    {
        return collect(range(1, 6))
            ->map(fn (int $number): array => [
                'title' => 'Example headline '.$number,
                'quote' => 'Synthetic template quote '.$number.'.',
                'author' => 'Example Customer '.$number,
                'context' => 'Template example only',
                'avatar' => null,
            ])
            ->all();
    }
}
