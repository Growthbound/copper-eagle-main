<?php

namespace App\Livewire\Admin;

use App\Support\Theme;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Theme')]
class ThemeEditor extends Component
{
    /** @var array<string, string> */
    public array $colors = [];

    public function mount(): void
    {
        $this->colors = array_intersect_key(Theme::palette(), config('theme.colors'));
    }

    public function save(): void
    {
        $this->validate(
            rules: array_fill_keys(
                array_map(fn (string $token): string => 'colors.'.$token, array_keys(config('theme.colors'))),
                ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ),
            messages: ['colors.*.regex' => 'Use a 6-digit hex color such as #2b1d16.'],
        );

        Theme::save($this->colors);

        session()->flash('status', 'Theme saved. Reload the public site to see it.');
    }

    public function resetToDefaults(): void
    {
        Theme::reset();

        $this->colors = Theme::defaults();

        session()->flash('status', 'Theme reset to the original palette.');
    }

    public function render(): View
    {
        return view('livewire.admin.theme-editor', [
            'definitions' => config('theme.colors'),
            'contrasts' => $this->contrasts(),
        ]);
    }

    /**
     * Live WCAG scores for each pair, so a hard-to-read palette is visible
     * before it reaches the public site.
     *
     * @return array<string, array{against: string, ratio: float, passes: bool}>
     */
    private function contrasts(): array
    {
        $resolved = $this->colors + config('theme.fixed');

        $scores = [];

        foreach (config('theme.colors') as $token => $definition) {
            $against = $definition['contrast_against'] ?? null;

            if ($against === null
                || ! Theme::isHex($resolved[$token] ?? null)
                || ! Theme::isHex($resolved[$against] ?? null)) {
                continue;
            }

            $ratio = Theme::contrast($resolved[$token], $resolved[$against]);

            $scores[$token] = [
                'against' => config('theme.colors.'.$against.'.label', ucfirst($against)),
                'ratio' => round($ratio, 2),
                'passes' => $ratio >= 4.5,
            ];
        }

        return $scores;
    }
}
