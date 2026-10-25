<?php

namespace Tests\Concerns;

use App\Content\ContentValidator;
use App\Content\ManagedContentFileRepository;
use App\Content\UrlDispositionRegistry;

/**
 * Hides the site's real managed pages and URL ledger from a test.
 *
 * The live pages in content/managed and the folds in
 * content/registries/url-intent-ledger.json are this site's content. Tests
 * about publication precedence, discovery files, or generated record pages are
 * about the platform, so they run as if neither existed. Page files in
 * content/pages and records are left alone; pair with UsesFixtureContent to
 * isolate those too.
 */
trait IsolatesManagedContent
{
    protected function setUpIsolatesManagedContent(): void
    {
        $missing = sys_get_temp_dir().'/isolated-managed-'.bin2hex(random_bytes(6));

        $this->app->instance(
            ManagedContentFileRepository::class,
            new ManagedContentFileRepository($this->app->make(ContentValidator::class), $missing),
        );

        UrlDispositionRegistry::flush();
        $this->app->instance(UrlDispositionRegistry::class, new UrlDispositionRegistry($missing.'/url-intent-ledger.json'));
    }

    /**
     * For a test in an isolated class that checks the real shipped content.
     */
    protected function restoreManagedContent(): void
    {
        UrlDispositionRegistry::flush();
        $this->app->forgetInstance(ManagedContentFileRepository::class);
        $this->app->forgetInstance(UrlDispositionRegistry::class);
    }

    protected function tearDownIsolatesManagedContent(): void
    {
        UrlDispositionRegistry::flush();
    }
}
