<?php

namespace App\Mail;

use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\PortalSignIn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A portal sign-in code, and a link that carries it. The link opens a page with a button, so a
 * mail scanner that opens links doesn't use the code up.
 */
class PortalSignInCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->code} is your ".app(SiteSettings::class)->business_name.' sign-in code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-sign-in-code',
            with: [
                'businessName' => app(SiteSettings::class)->business_name,
                'confirmUrl' => route('portal.sign-in.confirm', ['email' => $this->user->email, 'code' => $this->code]),
                'minutes' => PortalSignIn::CODE_MINUTES,
            ],
        );
    }
}
