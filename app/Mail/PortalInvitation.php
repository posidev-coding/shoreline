<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells someone they can follow their projects online. It carries no sign-in link that works
 * by itself: the link opens the sign-in page with their address filled in, and a code follows,
 * so a forwarded invitation doesn't let anyone else in.
 */
class PortalInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client, public User $user) {}

    public function envelope(): Envelope
    {
        $settings = app(SiteSettings::class);

        return new Envelope(
            replyTo: [new Address($settings->email, $settings->business_name)],
            subject: "Your projects with {$settings->business_name}, online",
        );
    }

    public function content(): Content
    {
        $settings = app(SiteSettings::class);

        return new Content(
            markdown: 'mail.portal-invitation',
            with: [
                'businessName' => $settings->business_name,
                'firstName' => str($this->user->name)->before(' ')->toString(),
                'signInUrl' => route('portal.login', ['email' => $this->user->email]),
                'phone' => $settings->hasPlaceholderPhone() ? null : $settings->phoneForDisplay(),
                'phoneHref' => $settings->phoneHref(),
            ],
        );
    }
}
