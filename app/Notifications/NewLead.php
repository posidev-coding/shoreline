<?php

namespace App\Notifications;

use App\Enums\Urgency;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Tells the owner about a new quote request, by email and in the admin's notification bell.
 * Replying to the email goes to the customer.
 */
class NewLead extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $lead = $this->lead;
        $message = (new MailMessage)
            ->subject($this->title())
            ->replyTo($lead->email, $lead->name)
            ->greeting($this->title())
            ->line("**{$lead->name}** · [".(new PhoneNumber($lead->phone, 'US'))->formatNational()."](tel:{$lead->phone}) · {$lead->email}")
            ->line("Prefers: {$lead->preferred_contact->getLabel()} · {$lead->urgency->getLabel()}")
            ->line("{$lead->street}, {$lead->town} {$lead->zip}");

        foreach ($lead->answerLines() as $line) {
            $message->line($line);
        }

        if (filled($lead->message)) {
            $message->line('“'.$lead->message.'”');
        }

        return $message
            ->action('Open the request', $this->url())
            ->line('Reply to this email to answer them directly.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body("{$this->lead->name}, {$this->lead->town}")
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([Action::make('open')->label('Open')->url($this->url())->markAsRead()])
            ->getDatabaseMessage();
    }

    private function title(): string
    {
        $service = $this->lead->service->name ?? 'Something else';

        return ($this->lead->urgency === Urgency::Emergency ? 'Urgent: ' : '')."Quote request: {$service}";
    }

    private function url(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->lead], panel: 'admin');
    }
}
