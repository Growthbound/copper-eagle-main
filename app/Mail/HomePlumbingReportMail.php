<?php

namespace App\Mail;

use App\Support\HomePlumbingReport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HomePlumbingReportMail extends Mailable
{
    /** @param array<string, string> $answers */
    public function __construct(public readonly string $firstName, private readonly array $answers) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your home plumbing report');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.home-plumbing-report');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => app(HomePlumbingReport::class)->pdf($this->answers), HomePlumbingReport::FILENAME)
                ->withMime('application/pdf'),
        ];
    }
}
