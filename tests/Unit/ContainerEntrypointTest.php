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
}
