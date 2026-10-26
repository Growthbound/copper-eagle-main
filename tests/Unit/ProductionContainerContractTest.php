<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionContainerContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_production_image_is_immutable_and_excludes_development_runtimes(): void
    {
        $dockerfile = $this->contents('Dockerfile.production');

        $this->assertStringContainsString('FROM node:22-bookworm-slim AS frontend', $dockerfile);
        $this->assertStringContainsString('FROM composer:2 AS vendor', $dockerfile);
        $this->assertStringContainsString('FROM dunglas/frankenphp:1-php8.5-bookworm AS runtime', $dockerfile);
        $this->assertStringContainsString('npm ci', $dockerfile);
        $this->assertStringContainsString('npm run build', $dockerfile);
        $this->assertStringContainsString('composer install', $dockerfile);
        $this->assertStringContainsString('--no-dev', $dockerfile);
        $this->assertStringContainsString('--optimize-autoloader', $dockerfile);
        $this->assertStringContainsString('mkdir -p storage/framework/cache/data storage/framework/views bootstrap/cache', $dockerfile);
        $this->assertStringContainsString('EXPOSE 8080', $dockerfile);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $dockerfile);
        $this->assertStringContainsString('DB_DATABASE=/data/database.sqlite', $dockerfile);
        $this->assertStringContainsString('XDG_CONFIG_HOME=/tmp', $dockerfile);
        $this->assertStringContainsString('HEALTHCHECK --interval=10s', $dockerfile);
        $this->assertStringContainsString('http://127.0.0.1:8080/up', $dockerfile);
        $this->assertStringContainsString('test ! -e /app/public/hot', $dockerfile);
        $this->assertStringContainsString('chown -R www-data:www-data /data /app/storage /app/bootstrap/cache', $dockerfile);
        $this->assertStringNotContainsString('npm run dev', $dockerfile);
        $this->assertStringNotContainsString('artisan serve', $dockerfile);
        $this->assertStringNotContainsString('COPY --from=frontend /app/node_modules', $dockerfile);
        $this->assertStringNotContainsString('COPY --chown=www-data:www-data', $dockerfile);
        $this->assertStringNotContainsString('chown -R www-data:www-data /app /data', $dockerfile);
    }

    public function test_production_compose_is_dokploy_native_and_persists_only_runtime_state(): void
    {
        $compose = $this->contents('compose.production.yaml');

        $this->assertStringContainsString('dockerfile: Dockerfile.production', $compose);
        $this->assertStringContainsString('expose:', $compose);
        $this->assertStringContainsString('"8080"', $compose);
        $this->assertStringContainsString('APP_KEY: ${APP_KEY:?Set APP_KEY in Dokploy}', $compose);
        $this->assertStringContainsString('APP_URL: ${APP_URL:?Set APP_URL in Dokploy}', $compose);
        $this->assertStringContainsString('APP_ENV: production', $compose);
        $this->assertStringContainsString('APP_DEBUG: "false"', $compose);
        $this->assertStringContainsString('DB_DATABASE: /data/database.sqlite', $compose);
        $this->assertStringContainsString('MAIL_MAILER: ${MAIL_MAILER:?Set MAIL_MAILER in Dokploy}', $compose);
        $this->assertStringContainsString('MAIL_HOST: ${MAIL_HOST:?Set MAIL_HOST in Dokploy}', $compose);
        $this->assertStringContainsString('MAIL_USERNAME: ${MAIL_USERNAME:?Set MAIL_USERNAME in Dokploy}', $compose);
        $this->assertStringContainsString('MAIL_PASSWORD: ${MAIL_PASSWORD:?Set MAIL_PASSWORD in Dokploy}', $compose);
        $this->assertStringContainsString('MAIL_SCHEME: ${MAIL_SCHEME:-smtp}', $compose);
        $this->assertStringNotContainsString('MAIL_SCHEME: ${MAIL_SCHEME:-tls}', $compose);
        $this->assertStringNotContainsString('MAIL_MAILER: log', $compose);
        $this->assertStringContainsString('database_data:/data', $compose);
        $this->assertStringContainsString('storage_data:/app/storage', $compose);
        $this->assertStringContainsString('http://127.0.0.1:8080/up', $compose);
        $this->assertStringContainsString('database_data:', $compose);
        $this->assertStringContainsString('storage_data:', $compose);
        $this->assertStringNotContainsString('.:/app', $compose);
        $this->assertDoesNotMatchRegularExpression('/^\s*ports:/m', $compose);
        $this->assertStringNotContainsString('container_name:', $compose);
        $this->assertStringNotContainsString('npm run dev', $compose);
        $this->assertStringNotContainsString('artisan serve', $compose);
    }

    public function test_production_compose_runs_the_laravel_scheduler_against_shared_state(): void
    {
        $compose = $this->contents('compose.production.yaml');

        $this->assertMatchesRegularExpression('/^\s{2}scheduler:\s*$/m', $compose);
        $this->assertStringContainsString('command: ["php", "artisan", "schedule:work"]', $compose);
        $this->assertStringContainsString('RUN_MIGRATIONS: "false"', $compose);
        $this->assertStringContainsString('depends_on:', $compose);
        $this->assertStringContainsString('condition: service_healthy', $compose);
        $this->assertGreaterThanOrEqual(2, substr_count($compose, 'database_data:/data'));
        $this->assertGreaterThanOrEqual(2, substr_count($compose, 'storage_data:/app/storage'));
    }

    /**
     * Compose passes only the variables it lists. A setting the app reads but
     * compose never forwards is silently empty in production, however it is
     * set in Dokploy. Each optional one reaches both services, and the
     * production example names it.
     */
    public function test_optional_production_settings_reach_both_services(): void
    {
        $compose = $this->contents('compose.production.yaml');
        $example = $this->contents('.env.production.example');

        foreach ([
            'WEBSITE_FORM_PROVIDER', 'WEBSITE_HIGHLEVEL_ORIGIN', 'WEBSITE_HIGHLEVEL_INQUIRY_FORM_ID',
            'WEBSITE_HIGHLEVEL_FEEDBACK_FORM_ID', 'WEBSITE_HIGHLEVEL_COMMENTS_FORM_ID',
            'LEAD_NOTIFICATION_RECIPIENTS', 'REFERRAL_FORM_URL',
            'GHL_PRIVATE_INTEGRATION_TOKEN', 'GHL_LOCATION_ID', 'GHL_COMPANY_ID', 'GHL_EXCLUDED_USER_IDS',
            'GHL_REFERRAL_CODE_FIELD_ID', 'GHL_REFERRAL_SOURCE_FIELD_ID', 'GHL_REFERRAL_BUSINESS_FIELD_ID',
            'GHL_REFERRAL_MAIN_CONTACT_FIELD_ID', 'GHL_REFERRAL_URL_FIELD_ID', 'GHL_REFERRAL_SERVICE_AREAS_FIELD_ID',
            'TRACKING_GA4_MEASUREMENT_ID', 'TRACKING_GTM_CONTAINER_ID', 'TRACKING_CLARITY_PROJECT_ID',
            'TRACKING_META_PIXEL_ID', 'TRACKING_CTM_ACCOUNT_ID', 'TRACKING_CANONICAL_HOST_ONLY',
        ] as $variable) {
            $this->assertSame(
                2,
                preg_match_all('/^\s+'.$variable.': \$\{'.$variable.':-[^}]*\}\s*$/m', $compose),
                $variable.' must be forwarded, optionally, by both services.',
            );
            $this->assertMatchesRegularExpression('/^'.$variable.'=/m', $example, $variable.' must be listed in .env.production.example.');
        }
    }

    public function test_deployment_runbook_lists_every_fail_closed_mail_sender_variable(): void
    {
        $runbook = $this->contents('docs/deployment/dokploy.md');

        $this->assertSame(1, preg_match(
            '/## Required environment variables.*?```dotenv\s+(.*?)```/s',
            $runbook,
            $matches,
        ));

        foreach ([
            'MAIL_MAILER=smtp',
            'MAIL_HOST=email-smtp.us-east-1.amazonaws.com',
            'MAIL_PORT=587',
            'MAIL_USERNAME=your-ses-smtp-username',
            'MAIL_PASSWORD=your-ses-smtp-password',
            'MAIL_SCHEME=smtp',
            'MAIL_FROM_ADDRESS=notifications@mail.youragency.com',
            'MAIL_FROM_NAME="Copper Eagle Plumbing"',
        ] as $variable) {
            $this->assertStringContainsString($variable, $matches[1]);
        }
    }

    public function test_production_startup_fails_closed_and_prepares_laravel(): void
    {
        $entrypoint = $this->contents('docker/production/entrypoint.sh');

        $this->assertStringContainsString('set -eu', $entrypoint);
        $this->assertStringContainsString('${APP_KEY:-}', $entrypoint);
        $this->assertStringContainsString('APP_KEY is required', $entrypoint);
        $this->assertStringContainsString('$app->make("encrypter")', $entrypoint);
        $this->assertStringContainsString('APP_KEY must be a valid Laravel encryption key', $entrypoint);
        $this->assertStringContainsString('${APP_ENV:-production}', $entrypoint);
        $this->assertStringContainsString('${APP_DEBUG:-false}', $entrypoint);
        $this->assertStringContainsString('APP_DEBUG must be false in production', $entrypoint);
        $this->assertStringContainsString('${MAIL_MAILER:-}', $entrypoint);
        $this->assertStringContainsString('MAIL_MAILER must be smtp in production', $entrypoint);
        $this->assertStringContainsString('${MAIL_HOST:-}', $entrypoint);
        $this->assertStringContainsString('${MAIL_USERNAME:-}', $entrypoint);
        $this->assertStringContainsString('${MAIL_PASSWORD:-}', $entrypoint);
        $this->assertStringContainsString('${MAIL_FROM_ADDRESS:-}', $entrypoint);
        $this->assertStringContainsString('${MAIL_FROM_NAME:-}', $entrypoint);
        $this->assertStringContainsString('SMTP mail requires MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME', $entrypoint);
        $this->assertStringContainsString('${DB_CONNECTION:-sqlite}', $entrypoint);
        $this->assertStringContainsString('${DB_DATABASE:-}', $entrypoint);
        $this->assertStringContainsString('SQLite DB_DATABASE must be an absolute path under /data', $entrypoint);
        $this->assertStringContainsString('touch "$database_path"', $entrypoint);
        $this->assertStringContainsString('${RUN_MIGRATIONS:-true}', $entrypoint);
        $this->assertStringContainsString('php artisan migrate --force --no-interaction', $entrypoint);
        $this->assertStringContainsString('php artisan optimize', $entrypoint);
        $this->assertStringContainsString('exec "$@"', $entrypoint);
        $this->assertStringNotContainsString('key:generate', $entrypoint);
    }

    /**
     * A single-container deploy (Dokploy's Application type) has no separate
     * scheduler service, so the app container can run one itself. It is off by
     * default: the Compose deploy already runs `scheduler`, and two schedulers
     * would run every task twice.
     */
    public function test_the_app_container_can_run_the_scheduler_when_asked(): void
    {
        $entrypoint = $this->contents('docker/production/entrypoint.sh');
        $compose = $this->contents('compose.production.yaml');
        $example = $this->contents('.env.production.example');
        $runbook = $this->contents('docs/deployment/dokploy.md');

        $this->assertStringContainsString('${RUN_SCHEDULER:-false}', $entrypoint);
        $this->assertStringContainsString('php artisan schedule:work', $entrypoint);
        $this->assertMatchesRegularExpression('/while true; do\s+php artisan schedule:work \|\| true/', $entrypoint, 'The scheduler must restart if it exits.');

        $scheduler = strpos($entrypoint, 'php artisan schedule:work');
        $this->assertGreaterThan(strpos($entrypoint, 'php artisan migrate --force --no-interaction'), $scheduler, 'The scheduler starts after migrations.');
        $this->assertGreaterThan(strpos($entrypoint, 'php artisan optimize'), $scheduler, 'The scheduler starts after the caches are built.');
        $this->assertLessThan(strrpos($entrypoint, 'exec "$@"'), $scheduler, 'The scheduler starts before the web server takes over.');

        $this->assertStringNotContainsString('RUN_SCHEDULER: "true"', $compose, 'Compose already runs the scheduler service.');
        $this->assertMatchesRegularExpression('/^RUN_SCHEDULER=/m', $example);
        $this->assertStringContainsString('## Deploying as a Dokploy Application', $runbook);
        $this->assertStringContainsString('RUN_SCHEDULER=true', $runbook);
    }

    public function test_phpunit_pins_the_application_url_independently_of_dotenv(): void
    {
        $phpunit = $this->contents('phpunit.xml');

        $this->assertStringContainsString(
            '<env name="APP_URL" value="http://localhost" force="true"/>',
            $phpunit,
        );
    }

    public function test_frankenphp_serves_only_public_files_with_production_headers(): void
    {
        $caddyfile = $this->contents('docker/production/Caddyfile');

        $this->assertStringContainsString(':8080', $caddyfile);
        $this->assertStringContainsString('persist_config off', $caddyfile);
        $this->assertStringContainsString('root * /app/public', $caddyfile);
        $this->assertStringContainsString('php_server', $caddyfile);
        $this->assertStringContainsString('encode zstd br gzip', $caddyfile);
        $this->assertStringContainsString('X-Frame-Options "SAMEORIGIN"', $caddyfile);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $caddyfile);
        $this->assertStringContainsString('max-age=31536000, immutable', $caddyfile);
        $this->assertStringContainsString('path_regexp hidden (^|/)\\.', $caddyfile);
        $this->assertStringContainsString('respond @hidden 404', $caddyfile);
        $this->assertStringNotContainsString('root * /app\n', $caddyfile);

        $dockerfile = $this->contents('Dockerfile.production');

        $this->assertStringContainsString('frankenphp fmt --overwrite /etc/caddy/Caddyfile', $dockerfile);
    }

    public function test_laravel_trusts_the_dokploy_proxy_headers(): void
    {
        $bootstrap = $this->contents('bootstrap/app.php');

        $this->assertStringContainsString("trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_TRAEFIK)", $bootstrap);
    }

    public function test_local_laravel_caches_cannot_leak_development_providers_into_the_image(): void
    {
        $dockerignore = $this->contents('.dockerignore');

        $this->assertStringContainsString('bootstrap/cache/*.php', $dockerignore);
        $this->assertStringContainsString('public/hot', $dockerignore);
        $this->assertStringContainsString('database/*.sqlite*', $dockerignore);
        $this->assertStringContainsString('storage', $dockerignore);
        $this->assertStringContainsString('auth.json', $dockerignore);
        $this->assertStringContainsString('.phpunit.result.cache', $dockerignore);
    }

    public function test_operator_documentation_uses_only_verified_business_facts(): void
    {
        $readme = $this->contents('README.md');

        $this->assertStringNotContainsString('config/business.php', $readme);
        $this->assertStringContainsString('config/site.php', $readme);
    }

    private function contents(string $path): string
    {
        $absolutePath = $this->root.'/'.$path;

        $this->assertFileExists($absolutePath);

        $contents = file_get_contents($absolutePath);

        $this->assertNotFalse($contents);

        return $contents;
    }
}
