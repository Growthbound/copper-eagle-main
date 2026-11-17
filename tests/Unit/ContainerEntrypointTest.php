<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ContainerEntrypointTest extends TestCase
{
    public function test_entrypoint_reconciles_named_dependency_volumes_to_current_lockfiles(): void
    {
        $entrypoint = file_get_contents(dirname(__DIR__, 2).'/docker/entrypoint.sh');

        $this->assertStringContainsString('sha256sum composer.lock', $entrypoint);
        $this->assertStringContainsString('vendor/.copper-eagle-composer-lock.sha256', $entrypoint);
        $this->assertStringContainsString('sha256sum package-lock.json', $entrypoint);
        $this->assertStringContainsString('node_modules/.copper-eagle-package-lock.sha256', $entrypoint);
    }

    public function test_entrypoint_explains_itself_when_the_project_is_not_mounted(): void
    {
        $entrypoint = file_get_contents(dirname(__DIR__, 2).'/docker/entrypoint.sh');

        // The development image ships no application source; compose.yaml bind-mounts
        // it. A host that builds the repository's root Dockerfile instead gets an
        // empty working directory, and the failure must name that cause rather than
        // leaving an operator to decode "sha256sum: composer.lock".
        $this->assertStringContainsString('if [ ! -f composer.json ] || [ ! -f composer.lock ]; then', $entrypoint);
        $this->assertStringContainsString('no application source', $entrypoint);
        $this->assertStringContainsString('Dockerfile.production', $entrypoint);
        $this->assertStringContainsString('RAILWAY_DOCKERFILE_PATH=Dockerfile.production', $entrypoint);

        // The guard is useless unless it precedes the first thing that reads the source.
        $this->assertLessThan(
            strpos($entrypoint, 'sha256sum composer.lock'),
            strpos($entrypoint, 'if [ ! -f composer.json ] || [ ! -f composer.lock ]; then'),
        );
    }
}
