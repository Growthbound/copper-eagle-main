<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentValidationFailed;
use App\Content\PageTemplateRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WritesRecordFixtures;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class PageTemplateSystemTest extends TestCase
{
    use ManagedContentFixtures;
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_fixed_templates_publish_in_registry_sequence_with_one_h1(): void
    {
        // The pillars' service-areas section needs at least one real area.
        $this->fixtureArea();

        foreach ([PageTemplate::ServicePillar, PageTemplate::LocationPillar, PageTemplate::DecisionGuide] as $template) {
            $slug = 'managed/'.$template->value;
            $title = 'Fictional '.$template->value;
            $user = User::factory()->create();
            $entry = app(ContentLifecycle::class)->createDraft(
                [
                    'kind' => ContentKind::Page,
                    'slug' => $slug,
                    'title' => $title,
                    'template' => $template,
                ],
                $this->fixedPayload($template, $title),
                $user,
            );
            app(ContentLifecycle::class)->publish($entry, $entry->revisions()->sole(), 0, $user);

            $response = $this->get('/'.$slug)->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, '<h1'));
            $this->assertSame(1, substr_count($html, 'class="site-header"'));

            $offset = -1;
            foreach (app(PageTemplateRegistry::class)->definition($template)->sequence as $section) {
                $next = strpos($html, 'data-page-section="'.$section.'"');
                $this->assertNotFalse($next, "Missing fixed section {$section}.");
                $this->assertGreaterThan($offset, $next, "Section {$section} rendered out of order.");
                $offset = $next;
            }
        }
    }

    public function test_missing_or_unknown_fixed_sections_fail_validation(): void
    {
        $template = PageTemplate::DecisionGuide;
        $payload = $this->fixedPayload($template);
        unset($payload['sections']['context']);
        $payload['sections']['arbitrary-component'] = ['type' => 'hero/split'];

        try {
            app(ContentLifecycle::class)->createDraft(
                [
                    'kind' => ContentKind::Page,
                    'slug' => 'managed/invalid',
                    'title' => 'Invalid fixed page',
                    'template' => $template,
                ],
                $payload,
                User::factory()->create(),
            );
            $this->fail('A fixed template must reject missing and unknown sections.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/sections/context', $failure->result->errors());
            $this->assertArrayHasKey('/sections/arbitrary-component', $failure->result->errors());
        }
    }

    public function test_fixed_sections_cannot_choose_their_rendered_block_type(): void
    {
        $payload = $this->fixedPayload(PageTemplate::DecisionGuide);
        $payload['sections']['context']['type'] = 'hero/split';

        try {
            app(ContentLifecycle::class)->createDraft(
                [
                    'kind' => ContentKind::Page,
                    'slug' => 'managed/rendering-directive',
                    'title' => 'Invalid rendering directive',
                    'template' => PageTemplate::DecisionGuide,
                ],
                $payload,
                User::factory()->create(),
            );
            $this->fail('Fixed section content must not choose its rendered block type.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/sections/context/type', $failure->result->errors());
        }
    }

    public function test_canonical_social_breadcrumb_and_visible_faq_metadata_follow_payload(): void
    {
        $template = PageTemplate::DecisionGuide;
        $payload = $this->fixedPayload($template, 'Fictional metadata guide');
        $user = User::factory()->create();
        $entry = app(ContentLifecycle::class)->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'managed/metadata-guide',
                'title' => 'Fictional metadata guide',
                'template' => $template,
            ],
            $payload,
            $user,
        );
        app(ContentLifecycle::class)->publish($entry, $entry->revisions()->sole(), 0, $user);

        $this->get('/managed/metadata-guide')
            ->assertOk()
            ->assertSee('rel="canonical" href="http://localhost/canonical/decision-guide"', false)
            ->assertSee('property="og:title" content="Fictional metadata guide"', false)
            ->assertSee('property="og:image" content="http://localhost/images/home/technician-detail.jpg"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }
}
