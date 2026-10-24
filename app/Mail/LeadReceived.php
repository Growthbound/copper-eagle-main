<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LeadReceived extends Mailable
{
    public function __construct(public readonly Lead $lead) {}

    public function envelope(): Envelope
    {
        $who = $this->lead->name !== '' ? $this->lead->name : 'Someone';

        return new Envelope(
            subject: 'New enquiry from '.$who,
            // Replying to the notification replies to the customer, which is
            // what the owner will try to do from their phone.
            replyTo: filled($this->lead->email)
                ? [new Address($this->lead->email, $this->lead->name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-received',
            with: [
                'lead' => $this->lead,
                'adminUrl' => route('admin.leads'),
            ],
        );
    }
}
