<?php

namespace App\Notifications;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\ClientMail;
use App\Support\MessageThread;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Emails the other side of a job's thread about new messages, a few minutes after one is posted.
 * Only the newest unread message's notification sends, listing everything unread, so a burst
 * sends one email; and someone who read it in the meantime gets none. The owner also gets it in
 * the admin's notification bell.
 */
#[DeleteWhenMissingModels]
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The most an email lists; the rest are a click away.
     */
    private const int MOST_SHOWN = 10;

    public function __construct(public ProjectMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->role->canAccessAdmin() ? ['mail', 'database'] : ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $project = $this->message->project;

        // A portal user who lost the job, or the portal, hears nothing more about it.
        if (! $notifiable->role->canAccessAdmin() && ! Project::query()->visibleInPortalTo($notifiable)->whereKey($project->getKey())->exists()) {
            return false;
        }

        return (int) MessageThread::unread($notifiable)->where('project_id', $project->getKey())->max('id') === $this->message->getKey();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $project = $this->message->project;
        $messages = $this->unread($notifiable);

        if (! $notifiable->role->canAccessAdmin()) {
            return ClientMail::message("New message about {$project->title}", 'mail.new-message', [
                'firstName' => ClientMail::firstName($notifiable->name),
                'title' => $project->title,
                'messages' => $this->lines($messages, $notifiable),
                'url' => route('portal.projects.show', $project->ulid).'#messages',
            ]);
        }

        $author = $this->message->author;
        $mail = (new MailMessage)
            ->subject("Message from {$this->from($this->message)}: {$project->title}")
            ->markdown('mail.client-message', [
                'from' => $this->from($this->message),
                'firstName' => $author ? ClientMail::firstName($author->name) : 'them',
                'title' => $project->title,
                'client' => $project->client->display_name,
                'messages' => $this->lines($messages, $notifiable),
                'url' => $this->adminUrl($project),
            ]);

        // A quick reply from his phone reaches the client instead of his own inbox.
        return $author ? $mail->replyTo($author->email, $author->name) : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $project = $this->message->project;

        return FilamentNotification::make()
            ->title("Message from {$this->from($this->message)}")
            ->body($project->title.': “'.str($this->message->body)->squish()->limit(120).'”')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->actions([Action::make('open')->label('Open')->url($this->adminUrl($project))->markAsRead()])
            ->getDatabaseMessage();
    }

    /**
     * @return Collection<int, ProjectMessage>
     */
    private function unread(User $notifiable): Collection
    {
        return MessageThread::unread($notifiable)
            ->where('project_id', $this->message->project_id)
            ->with('author')
            ->latest('id')
            ->take(self::MOST_SHOWN)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, ProjectMessage>  $messages
     * @return list<array{from: string, at: string, body: string}>
     */
    private function lines(Collection $messages, User $notifiable): array
    {
        return array_values($messages->map(fn (ProjectMessage $message): array => [
            'from' => $notifiable->role->canAccessAdmin() ? $this->from($message) : app(SiteSettings::class)->business_name,
            'at' => $message->created_at?->timezone(config('app.business_timezone'))->format('D, M j · g:i A') ?? '',
            'body' => $message->body,
        ])->all());
    }

    /**
     * Who sent a client's message, as the owner sees it.
     */
    private function from(ProjectMessage $message): string
    {
        return $message->author->name ?? $message->project->client->display_name;
    }

    private function adminUrl(Project $project): string
    {
        return ProjectResource::getUrl('view', ['record' => $project, 'relation' => 'messages'], panel: 'admin');
    }
}
