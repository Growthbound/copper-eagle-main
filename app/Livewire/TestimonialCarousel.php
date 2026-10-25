<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class TestimonialCarousel extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public int $active = 0;

    public string $carouselId = 'testimonial-carousel';

    public ?string $eyebrow = null;

    public string $heading = 'What customers say';

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function mount(
        array $items,
        string $carouselId = 'testimonial-carousel',
        ?string $eyebrow = null,
        string $heading = 'What customers say',
    ): void {
        $this->items = array_values($items);
        $this->carouselId = $carouselId;
        $this->eyebrow = $eyebrow;
        $this->heading = $heading;
    }

    public function select(int $index): void
    {
        if (array_key_exists($index, $this->items)) {
            $this->active = $index;
        }
    }

    /**
     * @return array<int, array{index: int, item: array<string, mixed>}>
     */
    public function visibleItems(): array
    {
        $count = count($this->items);

        if ($count <= 2) {
            return collect($this->items)
                ->map(fn (array $item, int $index): array => [
                    'index' => $index,
                    'item' => $item,
                ])
                ->values()
                ->all();
        }

        $offsets = $count >= 5
            ? [-2, -1, 0, 1, 2]
            : [-1, 0, 1];

        return collect($offsets)
            ->map(function (int $offset) use ($count): int {
                return ($this->active + $offset + $count) % $count;
            })
            ->unique()
            ->map(fn (int $index): array => [
                'index' => $index,
                'item' => $this->items[$index],
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.testimonial-carousel');
    }
}
