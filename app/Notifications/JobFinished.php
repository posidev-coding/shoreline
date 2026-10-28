<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use App\Support\ClientMail;
use App\Support\ClientNotices;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells a client's portal user their job is finished, a couple of minutes after it was marked
 * so, if it still is: a card dropped on Completed by mistake and moved back sends nothing.
 */
#[DeleteWhenMissingModels]
class JobFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public CarbonInterface $completedAt) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        // By the second: the database keeps no microseconds, and the time given here had them.
        return $this->project->completed_at?->timestamp === $this->completedAt->timestamp
            && ClientNotices::isVisible($this->project)
            && Project::query()->visibleInPortalTo($notifiable)->whereKey($this->project->getKey())->exists();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $project = $this->project;

        return ClientMail::message("Finished: {$project->title}", 'mail.job-finished', [
            'firstName' => ClientMail::firstName($notifiable->name),
            'title' => $project->title,
            'street' => $project->property->street,
            'hasFiles' => $project->sharedMedia('photos')->isNotEmpty() || $project->sharedMedia('documents')->isNotEmpty(),
            'url' => route('portal.projects.show', $project->ulid),
        ]);
    }
}
