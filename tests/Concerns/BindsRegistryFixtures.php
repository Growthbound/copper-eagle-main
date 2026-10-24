<?php

namespace Tests\Concerns;

use App\Content\LegacyUrlRegistry;
use App\Content\MediaIndex;
use App\Content\UrlDispositionRegistry;

/**
 * Fictional legacy-URL, intent-ledger and media-index registries for tests
 * about the mechanisms that read them.
 *
 * Those registries are site data. A site may ship none of them, and the one it
 * does ship reflects its own migration, so a test of the platform behaviour
 * binds a fixture copy into the container instead of reading
 * `content/registries/`. Nothing is written under `content/`, so tests running
 * alongside these never see the fixture.
 */
trait BindsRegistryFixtures
{
    protected function registryFixturePath(string $name): string
    {
        return base_path('tests/Fixtures/registries/'.$name);
    }

    protected function useMediaIndexFixture(): MediaIndex
    {
        $index = new MediaIndex($this->registryFixturePath('media-index.json'));

        $this->app->instance(MediaIndex::class, $index);

        return $index;
    }

    protected function useLedgerFixture(): UrlDispositionRegistry
    {
        $registry = new UrlDispositionRegistry($this->registryFixturePath('url-intent-ledger.json'));

        $this->app->instance(UrlDispositionRegistry::class, $registry);

        return $registry;
    }

    /**
     * The fixture registry declares an uploads prefix that resolves through
     * the media index, so the fixture index comes with it.
     */
    protected function useLegacyUrlFixture(): LegacyUrlRegistry
    {
        $registry = new LegacyUrlRegistry(
            $this->useMediaIndexFixture(),
            $this->registryFixturePath('legacy-urls.json'),
        );

        $this->app->instance(LegacyUrlRegistry::class, $registry);

        return $registry;
    }
}
