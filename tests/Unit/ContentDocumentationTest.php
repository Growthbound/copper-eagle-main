<?php

namespace Tests\Unit;

use App\Content\BlockRegistry;
use App\Content\Enums\ApiKeyScope;
use App\Content\PageTemplateRegistry;
use Tests\TestCase;

class ContentDocumentationTest extends TestCase
{
    public function test_content_documentation_covers_every_machine_contract_identifier(): void
    {
        $documentation = $this->documentation();

        foreach (array_keys(app(BlockRegistry::class)->all()) as $block) {
            $this->assertStringContainsString($block, $documentation, "Missing block documentation: {$block}");
        }

        foreach (array_keys(app(PageTemplateRegistry::class)->all()) as $template) {
            $this->assertStringContainsString(
                $template,
                $documentation,
                "Missing page-template documentation: {$template}",
            );
        }

        foreach (ApiKeyScope::cases() as $scope) {
            $this->assertStringContainsString(
                $scope->value,
                $documentation,
                "Missing API-scope documentation: {$scope->value}",
            );
        }
    }

    public function test_hermes_contract_documents_the_safety_and_recovery_rules(): void
    {
        $contract = file_get_contents(base_path('docs/content/hermes-content-contract.md'));
        $owner = file_get_contents(base_path('docs/content/owner-cms-runbook.md'));
        $recovery = file_get_contents(base_path('docs/content/publication-recovery.md'));

        $this->assertIsString($contract);
        $this->assertIsString($owner);
        $this->assertIsString($recovery);

        foreach ([
            '/api/content/v1/schema',
            'Idempotency-Key',
            'If-Match',
            'one H1',
            'draft',
            'publish',
            'preview token',
            'rollback',
            'media:read',
            'X-Request-ID',
            'content_validation_failed',
        ] as $required) {
            $this->assertStringContainsString($required, $contract);
        }

        foreach ([
            'Preview',
            'Publish',
            'Schedule',
            'History',
            'Rollback',
            'Media',
        ] as $required) {
            $this->assertStringContainsString($required, $owner);
        }

        foreach ([
            'php artisan content:check',
            'php artisan content:publish-scheduled',
            'cancel-schedule',
            'rollback',
            'request_id',
        ] as $required) {
            $this->assertStringContainsString($required, $recovery);
        }
    }

    public function test_documented_examples_never_contain_a_live_api_secret(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/site_live_[a-z0-9]+_[a-f0-9]{64}/',
            $this->documentation(),
        );
    }

    public function test_root_agent_instructions_define_the_platform_contract_without_owning_brand_voice(): void
    {
        $instructions = file_get_contents(base_path('AGENTS.md'));

        $this->assertIsString($instructions);

        foreach ([
            'docs/content/hermes-content-contract.md',
            '/api/content/v1/schema',
            'branches and pull requests',
            // Delegation, plus the file it delegates to. Naming the target is
            // what makes the contract actionable rather than a gesture at a
            // skill nobody can find.
            'Site-specific skills own brand voice',
            'docs/brand/voice.md',
            'composer test',
            'vendor/bin/pint --test',
            'npm run build',
            'php artisan content:check',
            'git diff --check',
        ] as $required) {
            $this->assertStringContainsString($required, $instructions);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/site_live_[a-z0-9]+_[a-f0-9]{64}/',
            $instructions,
        );
    }

    private function documentation(): string
    {
        return collect([
            'docs/content/hermes-content-contract.md',
            'docs/content/owner-cms-runbook.md',
            'docs/content/publication-recovery.md',
            'resources/views/components/blocks/README.md',
            'content/README.md',
        ])->map(fn (string $path): string => (string) file_get_contents(base_path($path)))
            ->implode("\n");
    }
}
