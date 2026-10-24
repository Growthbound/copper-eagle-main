<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class ManagedDiscoveryTest extends TestCase
{
    use ManagedContentFixtures;
    use RefreshDatabase;

    public function test_discovery_lists_only_published_indexable_managed_pages_once(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $published = $this->draft($lifecycle, $user, 'guides/published');
        $lifecycle->publish($published, $published->revisions()->sole(), 0, $user);
        $this->draft($lifecycle, $user, 'guides/draft');
        $scheduled = $this->draft($lifecycle, $user, 'guides/scheduled');
        $lifecycle->schedule(
            $scheduled,
            $scheduled->revisions()->sole(),
            CarbonImmutable::now()->addDay(),
            0,
            $user,
        );
        $noindex = $this->draft($lifecycle, $user, 'guides/private', true);
        $lifecycle->publish($noindex, $noindex->revisions()->sole(), 0, $user);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $llms = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertSame(1, substr_count($sitemap, 'http://localhost/canonical/decision-guide'));
        $this->assertSame(1, substr_count($llms, 'http://localhost/guides/published'));
        foreach (['draft', 'scheduled', 'private'] as $hidden) {
            $this->assertStringNotContainsString('/guides/'.$hidden, $sitemap);
            $this->assertStringNotContainsString('/guides/'.$hidden, $llms);
        }
    }

    private function draft(ContentLifecycle $lifecycle, User $user, string $slug, bool $noindex = false)
    {
        $payload = $this->fixedPayload(PageTemplate::DecisionGuide, 'Fictional '.$slug);
        $payload['noindex'] = $noindex;

        return $lifecycle->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => $slug,
                'title' => 'Fictional '.$slug,
                'template' => PageTemplate::DecisionGuide,
            ],
            $payload,
            $user,
        );
    }
}
