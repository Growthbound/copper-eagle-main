<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Jobs\NotifyOfLead;
use App\Livewire\QuoteForm;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\PublishesWebsiteFormFixtures;
use Tests\TestCase;

class WebsiteFormProviderTest extends TestCase
{
    use PublishesWebsiteFormFixtures;
    use RefreshDatabase;

    private const INQUIRY_FORM = 'ExampleInquiryForm01';

    protected function setUp(): void
    {
        parent::setUp();
        config(['website-forms.provider' => 'highlevel',
            'website-forms.origin' => 'https://api.leadconnectorhq.com',
            'website-forms.forms.inquiry' => self::INQUIRY_FORM,
            'site.phone' => '(555) 555-0123']);
    }

    public function test_both_layouts_embed_the_original_form_once_per_instance(): void
    {
        $html = Blade::render('<x-blocks.cta.quote-form id="one" heading="Request" /><x-blocks.cta.quote-form-split id="two" heading="Request" />');
        $this->assertSame(2, substr_count($html, 'data-form-id="'.self::INQUIRY_FORM.'"'));
        $this->assertStringNotContainsString('form_embed.js', $html);
        $this->assertStringNotContainsString('wire:submit', $html);
        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
    }

    public function test_stale_native_form_cannot_submit_after_provider_switch(): void
    {
        Bus::fake();
        Http::preventStrayRequests();
        config(['website-forms.provider' => 'native']);
        $form = Livewire::test(QuoteForm::class)->set('firstName', 'Test')->set('lastName', 'Visitor')->set('email', 'test@example.test');
        config(['website-forms.provider' => 'highlevel']);
        $form->call('submit')->assertHasErrors('provider')->assertSet('sent', false);
        $this->assertDatabaseCount('leads', 0);
        Bus::assertNotDispatched(NotifyOfLead::class);
        Http::assertNothingSent();
    }

    public function test_missing_or_unsafe_configuration_never_activates_native_intake(): void
    {
        foreach (['https://evil.test', 'javascript:alert(1)', 'https://api.leadconnectorhq.com@evil.test', 'https://api.leadconnectorhq.com/path', null] as $origin) {
            config(['website-forms.origin' => $origin]);
            $html = Blade::render('<x-blocks.cta.quote-form heading="Request" />');
            $this->assertStringNotContainsString('<iframe', $html);
            $this->assertStringNotContainsString('wire:submit', $html);
            $this->assertStringContainsString('tel:', $html);
        }
        config(['website-forms.provider' => 'typo']);
        Livewire::test(QuoteForm::class)->call('submit')->assertHasErrors('provider');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_preview_is_native_demo_only_even_when_highlevel_is_selected(): void
    {
        $this->get('/_templates/contact-form')->assertOk()
            ->assertSee('data-preview="true"', false)->assertDontSee('api.leadconnectorhq.com', false)->assertDontSee(self::INQUIRY_FORM, false);
        Livewire::test(QuoteForm::class, ['preview' => true])->set('firstName', 'Preview')->set('lastName', 'Visitor')->set('email', 'preview@example.test')->set('phone', '555 555 0100')
            ->call('submit')->assertSet('sent', true);
        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }

    public function test_managed_preview_does_not_expose_a_live_form_or_private_token(): void
    {
        $owner = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => ContentKind::Page,
            'slug' => 'form-preview', 'title' => 'Form preview',
            'template' => PageTemplate::Standard,
        ], ['title' => 'Form preview', 'blocks' => [
            ['type' => 'cta/quote-form', 'props' => ['heading' => 'Request']],
        ]], $owner);
        $issued = $lifecycle->issuePreview($entry, $entry->revisions()->sole(), 0, $owner);
        $this->get($issued->url())->assertOk()->assertSee('data-preview="true"', false)
            ->assertDontSee('api.leadconnectorhq.com', false)->assertDontSee('<iframe', false);
    }

    public function test_distinct_purposes_never_fall_back_to_the_sales_form(): void
    {
        foreach (['feedback', 'comments', 'unknown'] as $purpose) {
            $html = Blade::render('<x-blocks.cta.quote-form heading="Feedback" :purpose="$purpose" />', compact('purpose'));
            $this->assertStringNotContainsString(self::INQUIRY_FORM, $html);
            $this->assertStringNotContainsString('wire:submit', $html);
        }
    }

    public function test_separate_profiles_and_invalid_ids_fail_closed(): void
    {
        foreach ([null, '', 'short', str_repeat('a', 21), self::INQUIRY_FORM.'?'] as $id) {
            config(['website-forms.forms.inquiry' => $id]);
            $this->assertStringNotContainsString('<iframe', Blade::render('<x-blocks.cta.quote-form heading="Request" />'));
        }
        $this->publishFormPage('contact-us/feedback', 'Feedback', ['purpose' => 'feedback']);
        config(['website-forms.forms.inquiry' => self::INQUIRY_FORM,
            'website-forms.forms.feedback' => self::INQUIRY_FORM]);
        $this->get('/contact-us/feedback/')->assertOk()->assertDontSee(self::INQUIRY_FORM, false);
        config(['website-forms.forms.feedback' => 'AAAAAAAAAAAAAAAAAAAA']);
        $this->get('/contact-us/feedback/')->assertSee('data-form-id="AAAAAAAAAAAAAAAAAAAA"', false)
            ->assertDontSee(self::INQUIRY_FORM, false);
    }

    public function test_generated_inquiry_pages_use_the_provider_and_native_can_be_reactivated(): void
    {
        $this->useFixtureRecords();
        $this->publishFormPage('contact-us', 'Request');
        $this->publishFormPage('contact-us/feedback', 'Feedback', ['purpose' => 'feedback']);

        foreach (['/services/example-service/', '/areas/springfield/'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-form-id="'.self::INQUIRY_FORM.'"', false);
        }
        config(['website-forms.provider' => 'native']);
        $this->get('/contact-us/')->assertOk()->assertSee('wire:submit="submit"', false)
            ->assertDontSee('api.leadconnectorhq.com', false)->assertDontSee('<iframe', false);
        $this->get('/contact-us/feedback/')->assertOk()->assertDontSee('wire:submit', false);
    }

    public function test_legacy_page_mapped_to_a_purpose_is_held_out_of_sales_until_its_cms_revision_changes(): void
    {
        // A published revision that predates `purpose` would otherwise render
        // the sales form; the configured mapping keeps it on its own purpose.
        $this->publishFormPage('contact-us/feedback', 'Feedback');
        config(['website-forms.page_purposes' => ['contact-us/feedback' => 'feedback']]);

        $this->get('/contact-us/feedback/')->assertOk()
            ->assertDontSee(self::INQUIRY_FORM, false)->assertDontSee('wire:submit', false);

        config(['website-forms.forms.feedback' => 'AAAAAAAAAAAAAAAAAAAA']);
        $this->get('/contact-us/feedback/')->assertSee('data-form-id="AAAAAAAAAAAAAAAAAAAA"', false)
            ->assertDontSee(self::INQUIRY_FORM, false);

        // Without the mapping the same revision is an ordinary inquiry page.
        config(['website-forms.page_purposes' => []]);
        $this->get('/contact-us/feedback/')->assertSee('data-form-id="'.self::INQUIRY_FORM.'"', false);
    }
}
