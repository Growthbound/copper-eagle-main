<?php

namespace Tests\Feature\Livewire;

use App\Content\BenefitRepository;
use App\Livewire\BenefitCarousel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The carousel reads benefit records through the container, so each test
 * points the repository at its own directory of fixtures rather than at
 * whatever benefits a site has published in content/benefits.
 */
class BenefitCarouselTest extends TestCase
{
    private ?string $benefitDirectory = null;

    protected function tearDown(): void
    {
        if ($this->benefitDirectory !== null) {
            File::deleteDirectory($this->benefitDirectory);
            $this->benefitDirectory = null;
        }

        parent::tearDown();
    }

    public function test_next_wraps_from_the_last_card_to_the_first(): void
    {
        $this->useBenefits(self::benefits());

        Livewire::test(BenefitCarousel::class)
            ->set('active', 2)
            ->call('next')
            ->assertSet('active', 0);
    }

    public function test_previous_wraps_from_the_first_card_to_the_last(): void
    {
        $this->useBenefits(self::benefits());

        Livewire::test(BenefitCarousel::class)
            ->call('previous')
            ->assertSet('active', 2);
    }

    public function test_only_the_active_benefit_is_announced(): void
    {
        $this->useBenefits(self::benefits());

        Livewire::test(BenefitCarousel::class)
            ->assertSee('Arrives when promised')
            ->assertDontSee('Explains the work plainly')
            ->call('next')
            ->assertDontSee('Arrives when promised')
            ->assertSee('Explains the work plainly');
    }

    public function test_schema_authored_heading_copy_is_rendered(): void
    {
        $this->useBenefits(self::benefits());

        Livewire::test(BenefitCarousel::class, [
            'eyebrow' => 'Why customers choose us',
            'heading' => 'Care that respects your time',
        ])
            ->assertSee('Why customers choose us')
            ->assertSee('Care that respects your time')
            ->assertDontSee('What Professional Care Should Feel Like');
    }

    public function test_an_out_of_range_card_falls_back_to_the_first(): void
    {
        $this->useBenefits(self::benefits());

        Livewire::test(BenefitCarousel::class)
            ->set('active', 99)
            ->assertSet('active', 0)
            ->assertSee('Arrives when promised');
    }

    public function test_no_benefit_records_render_a_hidden_empty_panel_and_controls_do_not_fail(): void
    {
        $this->useBenefits([]);

        Livewire::test(BenefitCarousel::class)
            ->assertSet('active', 0)
            ->assertSeeHtml('aria-roledescription="carousel" hidden')
            ->assertDontSee('What Professional Care Should Feel Like')
            ->assertDontSee('Show next benefit')
            ->call('next')
            ->assertSet('active', 0)
            ->call('previous')
            ->assertSet('active', 0);
    }

    /**
     * Ordered against the alphabet, so the carousel follows the order field
     * rather than the record names.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function benefits(): array
    {
        return [
            'fixture-benefit-punctual' => [
                'name' => 'Arrives when promised',
                'summary' => 'Visits start inside the window we agreed.',
                'order' => 1,
            ],
            'fixture-benefit-plain' => [
                'name' => 'Explains the work plainly',
                'summary' => 'You hear what we found and what it means before anything starts.',
                'order' => 2,
            ],
            'fixture-benefit-tidy' => [
                'name' => 'Cleans up afterward',
                'summary' => 'The space is left the way we found it.',
                'order' => 3,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $records
     */
    private function useBenefits(array $records): void
    {
        $directory = storage_path('framework/testing/benefits-'.Str::random(12));

        File::ensureDirectoryExists($directory);

        foreach ($records as $slug => $data) {
            File::put($directory.'/'.$slug.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->benefitDirectory = $directory;

        $this->app->instance(BenefitRepository::class, new class($directory) extends BenefitRepository
        {
            public function __construct(private readonly string $fixtureDirectory) {}

            public function directory(): string
            {
                return $this->fixtureDirectory;
            }
        });
    }
}
