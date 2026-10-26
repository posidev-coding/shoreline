<?php

namespace App\Mail;

use App\Enums\Urgency;
use App\Models\Lead;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's confirmation of a quote request. It repeats nothing they typed, beyond a
 * first name that looks like one, so the form can't be used to send someone else a message
 * under our name.
 */
class QuoteRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $settings = app(SiteSettings::class);

        return new Envelope(
            replyTo: [new Address($settings->email, $settings->business_name)],
            subject: 'We got your quote request',
        );
    }

    public function content(): Content
    {
        $settings = app(SiteSettings::class);

        return new Content(
            markdown: 'mail.quote-request-received',
            with: [
                'businessName' => $settings->business_name,
                'service' => $this->lead->service->name ?? null,
                'isUrgent' => $this->lead->urgency === Urgency::Emergency,
                'phone' => $settings->hasPlaceholderPhone() ? null : $settings->phoneForDisplay(),
                'phoneHref' => $settings->phoneHref(),
            ],
        );
    }
}
