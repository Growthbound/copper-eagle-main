<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class PublicContentKindsTest extends TestCase
{
    use ManagedContentFixtures;
    use RefreshDatabase;

    public function test_articles_use_the_shared_consumer_and_appear_in_blog_discovery(): void
    {
        $this->publish(ContentKind::Article, 'blog/example-guide', 'Example Guide');

        $this->get('/blog/example-guide')
            ->assertOk()
            ->assertSee('Example Guide')
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('"@type":"Article"', false);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Example Guide')
            ->assertSee('/blog/example-guide');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('http://localhost/blog/')
            ->assertSee('http://localhost/canonical/decision-guide');
    }

    public function test_service_and_location_kinds_resolve_at_their_exact_public_paths(): void
    {
        $this->publish(ContentKind::Service, 'fixture-service', 'Fixture Service');
        $this->publish(ContentKind::Location, 'service-area/springfield', 'Springfield Service Area');

        $this->get('/fixture-service')->assertOk()->assertSee('Fixture Service');
        $this->get('/service-area/springfield')->assertOk()->assertSee('Springfield Service Area');
    }

    private function publish(ContentKind $kind, string $slug, string $title): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => $kind,
            'slug' => $slug,
            'title' => $title,
            'template' => PageTemplate::DecisionGuide,
        ], $this->fixedPayload(PageTemplate::DecisionGuide, $title), $user);

        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);
    }
}
