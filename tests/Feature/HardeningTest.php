<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    /**
     * A mistyped URL or a stale link still arrives from someone who wanted the
     * business. The page has to offer a way onward rather than dead-end.
     */
    public function test_a_missing_page_keeps_the_visitor_on_the_site(): void
    {
        $service = $this->fixtureService();

        $response = $this->get('/no-such-page')->assertNotFound();

        $response->assertSee('That page has moved or never existed.');
        $response->assertSee('Request a visit');
        // The service records are offered as a way onward.
        $response->assertSee('Fixture Service');
        $response->assertSee($service, false);
        $response->assertSee('name="robots" content="noindex', false);
    }

    public function test_the_error_page_reveals_nothing_about_the_application(): void
    {
        $content = $this->get('/no-such-page')->getContent();

        foreach (['vendor/', 'Stack trace', 'Illuminate\\', base_path()] as $leak) {
            $this->assertStringNotContainsString($leak, $content);
        }
    }

    /**
     * The 500 page must not depend on the database or the record files: those
     * are among the things that would have caused it.
     */
    public function test_the_server_error_page_needs_nothing_that_could_be_broken(): void
    {
        $rendered = view('errors.500')->render();

        $this->assertStringContainsString('Something went wrong at our end.', $rendered);

        foreach (['x-blocks', 'ServiceRepository', 'Setting::'] as $dependency) {
            $this->assertStringNotContainsString($dependency, file_get_contents(
                resource_path('views/errors/500.blade.php')
            ));
        }
    }

    public function test_the_maintenance_page_is_equally_self_contained(): void
    {
        $this->assertStringContainsString('Back shortly.', view('errors.503')->render());
    }

    /**
     * Debug output on a production site leaks environment variables and file
     * paths, so the container refuses to start with it enabled. This asserts
     * the guard is still in the entrypoint.
     */
    public function test_production_startup_refuses_to_run_with_debug_enabled(): void
    {
        $entrypoint = file_get_contents(base_path('docker/production/entrypoint.sh'));

        $this->assertStringContainsString('APP_DEBUG must be false in production', $entrypoint);
    }

    public function test_the_production_image_sends_hardened_headers(): void
    {
        $caddyfile = file_get_contents(base_path('docker/production/Caddyfile'));

        $this->assertStringContainsString('Strict-Transport-Security', $caddyfile);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $caddyfile);
        $this->assertStringContainsString('X-Frame-Options "SAMEORIGIN"', $caddyfile);
        $this->assertStringContainsString('Referrer-Policy', $caddyfile);

        // The exact PHP build is of no use to a visitor.
        $this->assertStringContainsString('-X-Powered-By', $caddyfile);
        $this->assertStringContainsString('expose_php = Off', file_get_contents(base_path('docker/php.ini')));
    }

    /**
     * Dotfiles under the web root — .env above all — must never be served.
     */
    public function test_hidden_files_are_refused_by_the_web_server(): void
    {
        $caddyfile = file_get_contents(base_path('docker/production/Caddyfile'));

        $this->assertStringContainsString('path_regexp hidden (^|/)\.', $caddyfile);
        $this->assertStringContainsString('respond @hidden 404', $caddyfile);
    }

    public function test_the_test_suite_is_actually_run_before_a_deploy(): void
    {
        $workflow = base_path('.github/workflows/ci.yml');

        $this->assertFileExists($workflow, 'Nothing runs the suite before the deploy branch builds.');

        $contents = file_get_contents($workflow);

        $this->assertStringContainsString('php artisan test', $contents);
        $this->assertStringContainsString('pint --test', $contents);
        $this->assertStringContainsString('content:check', $contents);
    }
}
