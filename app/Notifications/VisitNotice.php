<?php

namespace App\Notifications;

use App\Enums\VisitNoticeKind;
use App\Models\Project;
use App\Models\User;
use App\Support\ClientMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells a client's portal user that a visit is confirmed, has a new time, or is called off. It
 * carries the visit's type and window as text, since a called-off visit no longer exists.
 */
#[DeleteWhenMissingModels]
class VisitNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public VisitNoticeKind $kind,
        public string $visitType,
        public string $window,
        public ?string $previousWindow = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return Project::query()->visibleInPortalTo($notifiable)->whereKey($this->project->getKey())->exists();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $visit = mb_strtolower($this->visitType).' visit';
        $address = $this->project->property->street;

        return ClientMail::message("{$this->kind->subjectPrefix()}: {$this->window}", 'mail.visit-notice', [
            'firstName' => ClientMail::firstName($notifiable->name),
            'intro' => match ($this->kind) {
                VisitNoticeKind::Confirmed => "Your {$visit} is confirmed. We’ll arrive at {$address} in this window:",
                VisitNoticeKind::Moved => "Your {$visit} has a new time. We’ll now arrive at {$address} in this window:",
                VisitNoticeKind::CalledOff => "We’ve called off the {$visit} at {$address} that was set for:",
            },
            'window' => $this->window,
            'after' => match ($this->kind) {
                VisitNoticeKind::Confirmed => null,
                VisitNoticeKind::Moved => "It was {$this->previousWindow}.",
                VisitNoticeKind::CalledOff => 'We’ll be in touch about what comes next.',
            },
            'title' => $this->project->title,
            'url' => route('portal.projects.show', $this->project->ulid),
        ]);
    }
}
