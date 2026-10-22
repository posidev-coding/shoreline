<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent from inside CloudSmokeJob, so it already runs on a queue worker and isn't queued itself.
 */
class CloudSmokeMail extends Mailable
{
    public function __construct(public string $runId) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' mail check',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>This is a test message from <code>php artisan app:cloud-smoke</code> on '
                .e((string) config('app.url')).'.</p><p>Run: '.e($this->runId).'</p>',
        );
    }
}
