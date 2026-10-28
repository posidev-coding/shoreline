<?php

namespace App\Notifications;

use App\Filament\Pages\QuickBooks;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the super admins the QuickBooks connection needs them: it was refused, or its refresh
 * token is running out for good. By email and in the admin's notification bell.
 */
class QuickBooksNeedsAttention extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting($this->title)
            ->line($this->body)
            ->action('Open the QuickBooks page', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-exclamation-triangle')
            ->warning()
            ->actions([Action::make('open')->label('Open')->url($this->url())->markAsRead()])
            ->getDatabaseMessage();
    }

    private function url(): string
    {
        return QuickBooks::getUrl(panel: 'admin');
    }
}
