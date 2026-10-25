<?php

namespace Tests\Feature\Admin;

use App\Jobs\NotifyOfLead;
use App\Livewire\Admin\Webhooks as LeadDeliveryScreen;
use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LeadNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->actingAs(User::factory()->create());
    }

    public function test_saving_a_lead_schedules_a_deferred_notification(): void
    {
        Bus::fake();

        Lead::query()->create(['name' => 'Taylor Example']);

        Bus::assertDispatchedAfterResponse(NotifyOfLead::class);
    }

    public function test_the_owner_is_emailed_when_an_enquiry_arrives(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        $lead = $this->deliverNotificationFor(['name' => 'Taylor Example', 'email' => 'taylor@example.test']);

        Mail::assertSent(LeadReceived::class, fn (LeadReceived $mail): bool => $mail->lead->is($lead)
            && $mail->hasTo('owner@example.test'));
    }

    public function test_every_listed_address_is_notified(): void
    {
        LeadNotifications::save(true, "owner@example.test\ndispatch@example.test");

        $this->deliverNotificationFor();

        Mail::assertSent(LeadReceived::class, 2);
    }

    public function test_replying_to_the_notification_reaches_the_customer(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        $this->deliverNotificationFor(['name' => 'Taylor Example', 'email' => 'taylor@example.test']);

        Mail::assertSent(LeadReceived::class, function (LeadReceived $mail): bool {
            return $mail->hasReplyTo('taylor@example.test');
        });
    }

    public function test_an_enquiry_without_an_email_still_notifies(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        $this->deliverNotificationFor(['name' => 'Taylor Example', 'phone' => '555 555 0100']);

        Mail::assertSent(LeadReceived::class);
    }

    public function test_nothing_is_sent_when_notifications_are_switched_off(): void
    {
        LeadNotifications::save(false, 'owner@example.test');

        $this->deliverNotificationFor();

        Mail::assertNothingSent();
    }

    public function test_nothing_is_sent_when_no_recipient_is_configured(): void
    {
        config()->set('site.email', '');
        LeadNotifications::save(true, '');

        $this->deliverNotificationFor();

        Mail::assertNothingSent();
    }

    public function test_the_site_contact_address_is_used_when_no_recipient_is_listed(): void
    {
        config()->set('site.email', 'hello@example.test');
        LeadNotifications::save(true, '');

        $this->deliverNotificationFor();

        Mail::assertSent(LeadReceived::class, fn (LeadReceived $mail): bool => $mail->hasTo('hello@example.test'));
    }

    /**
     * A deployment can name the lead inbox in its environment. A list saved in
     * the admin still wins, and the site's contact address is the last resort.
     */
    public function test_the_environment_recipient_list_is_used_until_one_is_saved(): void
    {
        config()->set('site.email', 'hello@example.test');
        config()->set('website-forms.lead_notification_recipients', 'leads@example.test, office@example.test');
        LeadNotifications::save(true, '');

        $this->assertSame(['leads@example.test', 'office@example.test'], LeadNotifications::recipients());

        LeadNotifications::save(true, 'owner@example.test');

        $this->assertSame(['owner@example.test'], LeadNotifications::recipients());
    }

    public function test_notifications_are_on_before_anyone_configures_them(): void
    {
        $this->assertTrue(LeadNotifications::enabled());
    }

    public function test_a_malformed_address_is_rejected(): void
    {
        Livewire::test(LeadDeliveryScreen::class)
            ->set('notificationRecipients', "owner@example.test\nnot-an-address")
            ->call('saveNotifications')
            ->assertHasErrors('notificationRecipients');
    }

    public function test_a_test_email_can_be_sent_before_any_real_enquiry(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        Livewire::test(LeadDeliveryScreen::class)
            ->call('sendTestEmail')
            ->assertHasNoErrors();

        Mail::assertSent(LeadReceived::class);
    }

    public function test_a_test_email_needs_somewhere_to_go(): void
    {
        config()->set('site.email', '');
        LeadNotifications::save(true, '');

        Livewire::test(LeadDeliveryScreen::class)
            ->call('sendTestEmail')
            ->assertHasErrors('notificationRecipients');

        Mail::assertNothingSent();
    }

    /**
     * The admin has to say so when mail is only being logged, because
     * "notifications are on" and "notifications arrive" are different things.
     */
    public function test_the_admin_warns_when_no_mail_service_is_configured(): void
    {
        config()->set('mail.default', 'log');

        Livewire::test(LeadDeliveryScreen::class)
            ->assertSee('No mail service is set up');
    }

    /**
     * A configured driver is not a working one. A missing sender address is
     * accepted locally and rejected by the provider, which is the harder of the
     * two failures to notice.
     */
    public function test_the_admin_warns_when_mail_has_no_sender_address(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', null);

        Livewire::test(LeadDeliveryScreen::class)
            ->assertSee('no sender address')
            ->assertDontSee('No mail service is set up');
    }

    public function test_no_warning_once_mail_is_properly_configured(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.from.address', 'notifications@mail.example.com');

        Livewire::test(LeadDeliveryScreen::class)
            ->assertDontSee('No mail service is set up')
            ->assertDontSee('no sender address');
    }

    public function test_the_console_command_reports_a_rejected_send(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('554 Message rejected: Email address is not verified'));

        $this->artisan('mail:test', ['recipient' => 'owner@example.test'])
            ->expectsOutputToContain('not verified')
            ->assertFailed();
    }

    public function test_the_console_command_rejects_a_malformed_address(): void
    {
        $this->artisan('mail:test', ['recipient' => 'not-an-address'])->assertFailed();
    }

    public function test_the_console_command_says_when_it_only_wrote_to_the_log(): void
    {
        config()->set('mail.default', 'log');

        $this->artisan('mail:test', ['recipient' => 'owner@example.test'])
            ->expectsOutputToContain('Nothing was actually delivered')
            ->assertSuccessful();
    }

    public function test_a_failing_mail_service_does_not_break_the_submission(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $lead = Lead::withoutEvents(fn (): Lead => Lead::query()->create(['name' => 'Taylor Example']));

        (new NotifyOfLead($lead->id))->handle();

        $this->assertDatabaseHas('leads', ['name' => 'Taylor Example']);
    }

    /**
     * Getting SMTP right takes a couple of attempts, and this button is where
     * that happens. It has to report what the server actually said rather than
     * throwing.
     */
    public function test_a_failing_test_send_reports_what_the_server_said(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection could not be established with host smtp.example.com'));

        Livewire::test(LeadDeliveryScreen::class)
            ->call('sendTestEmail')
            ->assertHasErrors('notificationRecipients')
            ->assertSee('smtp.example.com');
    }

    /**
     * Sending uses credentials shared by every site built from this template,
     * so the sending domain's reputation is a shared asset. These three keep
     * one site from spending it.
     */
    public function test_the_recipient_list_is_capped(): void
    {
        $tooMany = collect(range(1, LeadNotifications::MAX_RECIPIENTS + 1))
            ->map(fn (int $i): string => 'person'.$i.'@example.test')
            ->implode("\n");

        Livewire::test(LeadDeliveryScreen::class)
            ->set('notificationRecipients', $tooMany)
            ->call('saveNotifications')
            ->assertHasErrors('notificationRecipients');
    }

    public function test_a_stored_list_beyond_the_cap_is_still_truncated_when_sending(): void
    {
        // Written straight to settings, bypassing the form's validation.
        LeadNotifications::save(true, collect(range(1, 20))
            ->map(fn (int $i): string => 'person'.$i.'@example.test')
            ->implode("\n"));

        $this->assertCount(LeadNotifications::MAX_RECIPIENTS, LeadNotifications::recipients());

        $this->deliverNotificationFor();

        Mail::assertSent(LeadReceived::class, LeadNotifications::MAX_RECIPIENTS);
    }

    public function test_the_test_button_cannot_be_used_to_send_repeatedly(): void
    {
        LeadNotifications::save(true, 'owner@example.test');

        $screen = Livewire::test(LeadDeliveryScreen::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $screen->call('sendTestEmail')->assertHasNoErrors();
        }

        $screen->call('sendTestEmail')->assertHasErrors('notificationRecipients');

        $this->assertStringContainsString(
            'Several tests sent already',
            $screen->errors()->first('notificationRecipients'),
        );
    }

    /**
     * The sending identity comes from the environment and is never written to
     * settings, so nothing an owner can reach in the admin changes who the
     * mail claims to be from.
     */
    public function test_the_sending_identity_is_not_reachable_from_the_admin(): void
    {
        $before = [config('mail.default'), config('mail.from.address'), config('mail.mailers.smtp.host')];

        Livewire::test(LeadDeliveryScreen::class)
            ->set('notificationRecipients', 'owner@example.test')
            ->call('saveNotifications');

        $this->assertSame($before, [config('mail.default'), config('mail.from.address'), config('mail.mailers.smtp.host')]);

        foreach (['mail.default', 'mail.from.address', 'mail.mailers.smtp.host', 'mail.mailers.smtp.password'] as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverNotificationFor(array $attributes = []): Lead
    {
        $lead = Lead::withoutEvents(
            fn (): Lead => Lead::query()->create($attributes + ['name' => 'Taylor Example'])
        );

        (new NotifyOfLead($lead->id))->handle();

        return $lead;
    }
}
