<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ResendTransport;
use Tests\TestCase;

class ResendMailTransportTest extends TestCase
{
    public function test_the_configured_resend_api_mailer_can_boot_without_smtp(): void
    {
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', 're_test_not_a_real_api_key');

        // Mail::fake() hides a missing production transport dependency. Resolve
        // the real driver without sending a message or opening an SMTP socket.
        $this->assertTrue(class_exists(\Resend::class), 'The Resend API mailer requires its production SDK.');

        $transport = app('mail.manager')->mailer()->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
        $this->assertSame('resend', (string) $transport);
    }
}
