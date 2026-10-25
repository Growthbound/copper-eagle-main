<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ThemeCssContractTest extends TestCase
{
    public function test_theme_exposes_one_runtime_editable_semantic_palette(): void
    {
        $theme = $this->contents('resources/css/theme.css');

        foreach ([
            'primary' => 'primary',
            'accent' => 'accent',
            'page' => 'page',
            'surface' => 'surface',
            'text' => 'text',
            'muted' => 'text-muted',
            'on-primary' => 'on-primary',
            'on-accent' => 'on-accent',
            'accent-on-page' => 'accent-on-page',
            'accent-on-primary' => 'accent-on-primary',
            'border' => 'border',
            'danger' => 'danger',
        ] as $token => $alias) {
            $this->assertStringContainsString('--theme-'.$token.':', $theme);
            $this->assertStringContainsString('--color-'.$alias.': var(--theme-'.$token.');', $theme);
        }

        foreach ([
            'primary' => 'teal',
            'accent' => 'orange',
            'page' => 'white',
            'surface' => 'mist',
            'text' => 'ink',
            'muted' => 'muted',
            'on-primary' => 'white',
            // Copper buttons carry white text (docs/brand/design-system.md).
            'on-accent' => 'white',
            'accent-on-page' => 'orange-ink',
            'accent-on-primary' => 'orange-light',
        ] as $semantic => $editable) {
            $this->assertStringContainsString('--theme-'.$semantic.': var(--color-'.$editable.');', $theme);
        }

        $this->assertStringContainsString('@theme inline', $theme);
        $this->assertStringContainsString('color-mix(', $theme);
    }

    public function test_application_styles_and_marketing_templates_have_no_raw_color_contracts(): void
    {
        $application = $this->contents('resources/css/app.css');

        // The homepage is no longer a single file: it is assembled from blocks,
        // so the contract now covers every block template plus the site chrome
        // they render inside. New blocks are picked up without editing this.
        $templates = collect(glob(dirname(__DIR__, 2).'/resources/views/components/blocks/*/*.blade.php'))
            ->map(fn (string $path): string => (string) file_get_contents($path))
            ->prepend($this->contents('resources/views/components/layouts/marketing.blade.php'))
            ->prepend($this->contents('resources/views/components/site-header.blade.php'))
            ->prepend($this->contents('resources/views/components/site-footer.blade.php'))
            ->prepend($this->contents('resources/views/components/site-footers/structured.blade.php'))
            ->prepend($this->contents('resources/views/livewire/benefit-carousel.blade.php'))
            ->prepend($this->contents('resources/views/livewire/testimonial-carousel.blade.php'))
            ->prepend($this->contents('resources/views/livewire/quote-form.blade.php'))
            ->implode("\n");

        $this->assertStringContainsString('.review-carousel-panel', $application);
        $this->assertStringContainsString('.review-carousel-card--video', $application);
        $this->assertStringContainsString('.structured-footer', $application);
        $this->assertStringContainsString('.contact-split-panel', $application);
        $this->assertStringContainsString('.cta-image-split', $application);
        $this->assertStringContainsString('.team-grid-section', $application);
        $this->assertStringContainsString('.story-checklist-panel', $application);
        $this->assertStringContainsString('.template-gallery', $application);
        $this->assertStringContainsString('.rich-content-reading', $application);
        $this->assertStringContainsString('.direct-answer-card', $application);
        $this->assertStringContainsString('.coverage-section', $application);
        $this->assertStringContainsString('.sticky-mobile-cta', $application);
        $this->assertStringContainsString('.service-hero', $application);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(/i', $application);
        $this->assertDoesNotMatchRegularExpression('/:\s*(?:white|black)\s*(?:;|,|\))/i', $application);
        $this->assertStringNotContainsString('text-orange', $templates);
        $this->assertStringNotContainsString('text-white', $templates);
        $this->assertStringNotContainsString('bg-white', $templates);
        $this->assertStringNotContainsString('text-ink', $templates);
    }

    public function test_semantic_aliases_do_not_replace_legacy_editor_tokens(): void
    {
        $theme = $this->contents('resources/css/theme.css');

        $this->assertMatchesRegularExpression('/--color-muted: #[0-9a-f]{6};/', $theme);
        $this->assertStringContainsString('--color-text-muted: var(--theme-muted);', $theme);
        $this->assertStringNotContainsString('--color-muted: var(--theme-muted);', $theme);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        $this->assertNotFalse($contents);

        return $contents;
    }
}
