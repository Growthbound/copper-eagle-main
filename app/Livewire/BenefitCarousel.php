<?php

namespace App\Livewire;

use App\Content\Benefit;
use App\Content\BenefitRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BenefitCarousel extends Component
{
    public int $active = 0;

    #[Locked]
    public string $carouselId = 'benefits';

    #[Locked]
    public string $eyebrow = 'The service standard';

    #[Locked]
    public string $heading = 'What Professional Care Should Feel Like';

    public function mount(
        string $carouselId = 'benefits',
        ?string $eyebrow = null,
        ?string $heading = null,
    ): void {
        $this->carouselId = preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $carouselId) === 1
            ? $carouselId
            : 'benefits';
        $this->eyebrow = filled($eyebrow)
            ? trim($eyebrow)
            : 'The service standard';
        $this->heading = filled($heading)
            ? trim($heading)
            : 'What Professional Care Should Feel Like';
    }

    public function previous(): void
    {
        $this->move(-1);
    }

    public function next(): void
    {
        $this->move(1);
    }

    public function render(): View
    {
        $benefits = $this->benefits()
            ->map(fn (Benefit $benefit): array => [
                'title' => $benefit->name(),
                'body' => $benefit->summary(),
            ])
            ->values()
            ->all();

        // The index is public state, so it can arrive out of range — from a
        // crafted update, or because a record was removed since the last
        // render. Fall back to the first card rather than indexing past the end.
        if (! array_key_exists($this->active, $benefits)) {
            $this->active = 0;
        }

        return view('livewire.benefit-carousel', [
            'benefits' => $benefits,
        ]);
    }

    /**
     * Steps through the cards, wrapping at either end. With no benefit records
     * there is nothing to step through, and a modulo by zero would throw.
     */
    private function move(int $step): void
    {
        $count = $this->benefits()->count();

        if ($count === 0) {
            $this->active = 0;

            return;
        }

        $this->active = (($this->active + $step) % $count + $count) % $count;
    }

    /** @return Collection<int, Benefit> */
    private function benefits(): Collection
    {
        return app(BenefitRepository::class)->all();
    }
}
