<?php

namespace Tests\Feature;

use App\Content\ProcessStepRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IconProcessStepsBlockTest extends TestCase
{
    private ?string $processDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire keeps a static stack of the components it is rendering and
        // wraps every Blade conditional in morph markers while that stack is
        // non-empty. A Livewire render that threw in an earlier test never
        // pops its entry, so these plain Blade renders would pick up markers
        // depending on test order. Start each test from a clean stack.
        Livewire::flushState();
    }

    protected function tearDown(): void
    {
        if ($this->processDirectory !== null) {
            File::deleteDirectory($this->processDirectory);
            $this->processDirectory = null;
        }

        parent::tearDown();
    }

    public function test_it_renders_an_icon_led_process_from_explicit_steps(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-process-variant="icon-grid"', $html);
        $this->assertSame(3, substr_count($html, '<article'));
        $this->assertSame(3, substr_count($html, 'class="marketing-icon process-icon-grid-symbol"'));
        $this->assertStringContainsString('data-step-icon="magnifying-glass"', $html);
        $this->assertStringContainsString('data-step-icon="calendar-days"', $html);
        $this->assertStringContainsString('data-step-icon="check-circle"', $html);
        $this->assertStringContainsString('Select your service', $html);
        $this->assertStringContainsString('Choose your date', $html);
        $this->assertStringContainsString('Confirm and relax', $html);
    }

    public function test_it_uses_the_existing_process_repository_when_steps_are_not_provided(): void
    {
        $this->useProcessSteps([
            'fixture-step-assess' => ['name' => 'Assess', 'summary' => 'We look at the job first.', 'order' => 1],
            'fixture-step-schedule' => ['name' => 'Schedule', 'summary' => 'We agree a time that suits you.', 'order' => 2],
            'fixture-step-complete' => ['name' => 'Complete', 'summary' => 'We finish and walk you through it.', 'order' => 3],
        ]);

        $html = Blade::render(
            '<x-blocks.process-steps.icon-grid heading="A clear process" />',
        );

        $this->assertStringContainsString('Assess', $html);
        $this->assertStringContainsString('Schedule', $html);
        $this->assertStringContainsString('Complete', $html);
        $this->assertSame(3, substr_count($html, '<article'));
        $this->assertSame(3, substr_count($html, 'process-icon-grid-symbol'));
    }

    public function test_an_explicit_empty_step_list_renders_no_section(): void
    {
        $html = Blade::render(
            '<x-blocks.process-steps.icon-grid heading="No process" :steps="[]" />',
        );

        $this->assertSame('', trim($html));
    }

    public function test_copy_is_escaped_and_optional_intro_omits_cleanly(): void
    {
        $steps = [[
            'icon' => 'sparkles',
            'title' => '<Great result>',
            'body' => '<Careful work>',
        ]];

        $html = Blade::render(
            '<x-blocks.process-steps.icon-grid heading="<Process>" :steps="$steps" />',
            ['steps' => $steps],
        );

        $this->assertStringContainsString('&lt;Process&gt;', $html);
        $this->assertStringContainsString('&lt;Great result&gt;', $html);
        $this->assertStringContainsString('&lt;Careful work&gt;', $html);
        $this->assertStringNotContainsString('process-icon-grid-intro', $html);
    }

    public function test_the_existing_numbered_grid_variant_still_renders(): void
    {
        $html = Blade::render(
            '<x-blocks.process-steps.grid heading="Existing process" :steps="$steps" />',
            ['steps' => [
                ['number' => '01', 'title' => 'Existing step', 'body' => 'Existing body.'],
            ]],
        );

        $this->assertStringContainsString('Existing process', $html);
        $this->assertStringContainsString('Existing step', $html);
        $this->assertStringContainsString('01', $html);
    }

    /**
     * Points the process repository at its own directory of fixture records,
     * so the step count is exactly what this test wrote.
     *
     * @param  array<string, array<string, mixed>>  $records
     */
    private function useProcessSteps(array $records): void
    {
        $directory = storage_path('framework/testing/process-'.Str::random(12));

        File::ensureDirectoryExists($directory);

        foreach ($records as $slug => $data) {
            File::put($directory.'/'.$slug.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->processDirectory = $directory;

        $this->app->instance(ProcessStepRepository::class, new class($directory) extends ProcessStepRepository
        {
            public function __construct(private readonly string $fixtureDirectory) {}

            public function directory(): string
            {
                return $this->fixtureDirectory;
            }
        });
    }

    private function render(): string
    {
        return Blade::render(
            <<<'BLADE'
                <x-blocks.process-steps.icon-grid
                    eyebrow="Simple from start to finish"
                    heading="Three easy steps"
                    body="A clear path from first call to finished job."
                    :steps="$steps"
                />
            BLADE,
            ['steps' => [
                [
                    'icon' => 'magnifying-glass',
                    'title' => 'Select your service',
                    'body' => 'Start with the option that fits what you need.',
                ],
                [
                    'icon' => 'calendar-days',
                    'title' => 'Choose your date',
                    'body' => 'Pick the appointment window that works for you.',
                ],
                [
                    'icon' => 'check-circle',
                    'title' => 'Confirm and relax',
                    'body' => 'We will take it from there and keep you informed.',
                ],
            ]],
        );
    }
}
