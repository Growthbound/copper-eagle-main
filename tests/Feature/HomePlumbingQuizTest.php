<?php

namespace Tests\Feature;

use App\Content\PublicLinkTarget;
use App\Jobs\NotifyOfLead;
use App\Jobs\SendHomePlumbingReport;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HomePlumbingQuizTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/home-plumbing-quiz';

    public function test_a_visitor_cannot_skip_questions_or_submit_unknown_answers(): void
    {
        $this->get(self::URL)->assertOk()->assertSee('Home plumbing quiz');
        $this->get(self::URL.'/report')->assertRedirect(self::URL);
        $this->post(self::URL)->assertRedirect(self::URL.'/home');

        $this->get(self::URL.'/contact')->assertRedirect(self::URL.'/home');
        $this->from(self::URL.'/home')->post(self::URL.'/home', ['answer' => 'invalid'])
            ->assertSessionHasErrors('answer');
        $this->assertDatabaseEmpty('leads');
    }

    public function test_only_the_start_page_can_be_linked_from_managed_content(): void
    {
        $links = app(PublicLinkTarget::class);

        $this->assertTrue($links->exists(self::URL));
        $this->assertFalse($links->exists(self::URL.'/report'));
    }

    public function test_only_the_public_start_page_is_in_the_sitemap_and_the_menus_leave_it_unlisted(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $sitemap->assertSee('<loc>http://localhost'.self::URL.'</loc>', false)
            ->assertDontSee(self::URL.'/')
            ->assertDontSee('/_templates'.self::URL);

        $this->get('/')->assertOk()->assertDontSee(self::URL);
        $this->get(self::URL)->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('rel="canonical" href="http://localhost'.self::URL.'"', false)
            ->assertDontSee('content="noindex', false);
        $this->post(self::URL)->assertRedirect(self::URL.'/home');
        $this->get(self::URL.'/home')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        config()->set('search.canonical_host', 'www.example.com');
        $this->get(self::URL)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_last_form_requests_the_report_and_optional_inspection_once(): void
    {
        Bus::fake([NotifyOfLead::class, SendHomePlumbingReport::class]);
        config()->set('integrations.defaults.clarity_project_id', 'testproject');
        $this->get(self::URL)->assertSee('clarity-project-id', false);
        $this->answerQuestions();
        $this->get(self::URL.'/water-heater')->assertSee('clarity-project-id', false)
            ->assertSee('data-clarity-mask="true"', false);
        $this->get(self::URL.'/contact')->assertSee('Where can we send your report?')
            ->assertSee('Want a free home plumbing inspection?')->assertDontSee('clarity-project-id', false);
        $this->from(self::URL.'/contact')->post(self::URL.'/contact', ['email' => 'wrong'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);
        $this->submitContact(inspection: true);

        $lead = Lead::query()->sole();
        $this->assertSame('Taylor', $lead->first_name);
        $this->assertSame('Example', $lead->last_name);
        $this->assertSame('taylor@example.test', $lead->email);
        $this->assertSame('801-555-0100', $lead->phone);
        $this->assertSame('new', $lead->status);
        $this->assertSame('tank-10-plus', $lead->quiz_answers['water-heater']);
        $this->assertStringContainsString('Inspection: requested', $lead->message);

        $this->get(self::URL.'/complete')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('Your plumbing report is ready')
            ->assertSee('Plan for a new water heater')
            ->assertSee('Recurring slow drains')
            ->assertSee('We’ll call to find a time')
            ->assertDontSee('clarity-project-id', false)
            ->assertDontSee('taylor@example.test');

        $this->post(self::URL.'/contact', ['inspection' => '1'])->assertRedirect(self::URL.'/complete');
        $this->assertDatabaseCount('leads', 1);
        Bus::assertDispatchedAfterResponseTimes(NotifyOfLead::class, 1);
        Bus::assertDispatchedAfterResponseTimes(SendHomePlumbingReport::class, 1);
    }

    public function test_the_report_is_sent_as_a_pdf_even_without_an_inspection_request(): void
    {
        config()->set('mail.default', 'report-test');
        config()->set('mail.mailers.report-test', ['transport' => 'array']);
        $this->answerQuestions();
        $this->submitContact();

        $lead = Lead::query()->sole();
        $this->assertSame('archived', $lead->status);
        $this->assertStringContainsString('Inspection: declined', $lead->message);
        $this->assertNotNull($lead->quiz_report_sent_at);
        $this->assertSame(1, $lead->quiz_report_attempts);

        $transport = Mail::mailer()->getSymfonyTransport();
        $email = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame('taylor@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('Your home plumbing report', $email->getSubject());
        $this->assertCount(1, $email->getAttachments());
        $pdf = $email->getAttachments()[0];
        $this->assertSame('home-plumbing-report.pdf', $pdf->getFilename());
        $this->assertSame('application/pdf', $pdf->getMediaType().'/'.$pdf->getMediaSubtype());
        $this->assertStringStartsWith('%PDF-', $pdf->getBody());
        $this->artisan('quiz:send-reports')->assertSuccessful();
        $this->assertCount(1, $transport->messages());

        $this->get(self::URL.'/complete')->assertOk()->assertSee('Download my PDF')->assertDontSee('We’ll call to find a time');
        $download = $this->get(self::URL.'/report')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
    }

    public function test_the_scheduler_retries_a_report_after_the_attempt_window(): void
    {
        Bus::fake([SendHomePlumbingReport::class]);
        $this->answerQuestions();
        $this->submitContact();
        $lead = Lead::query()->sole();
        $lead->forceFill(['quiz_report_attempted_at' => now(), 'quiz_report_attempts' => 1])->saveQuietly();

        config()->set('mail.default', 'report-test');
        config()->set('mail.mailers.report-test', ['transport' => 'array']);
        $this->assertFalse(SendHomePlumbingReport::due()->exists());
        $this->travel(5)->minutes();

        (new SendHomePlumbingReport($lead->id))->handle();
        $lead->refresh();
        $this->assertNotNull($lead->quiz_report_sent_at);
        $this->assertSame(2, $lead->quiz_report_attempts);
    }

    public function test_the_template_preview_is_listed_and_cannot_write_leads(): void
    {
        Mail::fake();
        $preview = '/_templates/home-plumbing-quiz';
        $this->get('/templates')->assertOk()->assertSee($preview);
        $this->get($preview)->assertOk()->assertSee('noindex');
        $this->answerQuestions($preview);
        $this->submitContact($preview, inspection: true);
        $this->get($preview.'/complete')->assertOk()->assertSee('Preview complete');
        $this->get($preview.'/report')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseEmpty('leads');
        Mail::assertNothingSent();

        $this->app->detectEnvironment(fn () => 'production');
        $this->get($preview)->assertNotFound();
        $this->withSession(['_token' => 'preview-csrf-token'])
            ->post($preview.'/contact', ['_token' => 'preview-csrf-token'])->assertNotFound();
    }

    private function answerQuestions(string $url = self::URL): void
    {
        $this->post($url)->assertRedirect($url.'/home');
        foreach ([
            ['home', 'older', 'water-heater'],
            ['water-heater', 'tank-10-plus', 'hot-water'],
            ['hot-water', 'runs-out', 'drains'],
            ['drains', 'repeated', 'leaks'],
            ['leaks', 'none', 'minerals'],
            ['minerals', 'yes', 'contact'],
        ] as [$step, $answer, $next]) {
            $this->get($url.'/'.$step)->assertOk();
            $this->post($url.'/'.$step, ['answer' => $answer])->assertRedirect($url.'/'.$next);
        }
    }

    private function submitContact(string $url = self::URL, bool $inspection = false): void
    {
        $this->post($url.'/contact', [
            'first_name' => 'Taylor',
            'last_name' => 'Example',
            'email' => 'taylor@example.test',
            'phone' => '801-555-0100',
            ...($inspection ? ['inspection' => '1'] : []),
        ])->assertRedirect($url.'/complete');
    }
}
