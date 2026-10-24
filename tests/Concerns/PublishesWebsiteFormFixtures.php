<?php

namespace Tests\Concerns;

use App\Content\AreaRepository;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\ServiceRepository;
use App\Models\User;

/**
 * Fictional pages that carry the website form, for tests about how the form
 * is chosen and embedded rather than about any one site's pages.
 *
 * Pages are published into the test database and records are read from
 * tests/Fixtures, so nothing is written under content/.
 */
trait PublishesWebsiteFormFixtures
{
    /**
     * Publish a managed page holding one quote-form block.
     *
     * @param  array<string, mixed>  $formProps
     */
    protected function publishFormPage(string $slug, string $title, array $formProps = []): void
    {
        $owner = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => ContentKind::Page,
            'slug' => $slug,
            'title' => $title,
            'template' => PageTemplate::Standard,
        ], ['title' => $title, 'blocks' => [
            ['type' => 'cta/quote-form', 'props' => ['heading' => $title, ...$formProps]],
        ]], $owner);

        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $owner);
    }

    /**
     * Serve service and area records from tests/Fixtures/records so their
     * generated pages exist without touching content/.
     */
    protected function useFixtureRecords(): void
    {
        $this->app->instance(ServiceRepository::class, new class extends ServiceRepository
        {
            public function directory(): string
            {
                return base_path('tests/Fixtures/records/services');
            }
        });

        $this->app->instance(AreaRepository::class, new class extends AreaRepository
        {
            public function directory(): string
            {
                return base_path('tests/Fixtures/records/areas');
            }
        });
    }
}
