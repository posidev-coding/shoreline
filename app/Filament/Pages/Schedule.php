<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ScheduleCalendar;
use App\Models\User;
use App\Models\Visit;
use App\Support\VisitFeed;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * Every visit on a calendar, the same-day truth for the schedule. The phone's calendar app gets
 * them from the visits feed, hours behind when it's Google Calendar.
 */
class Schedule extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Schedule';

    /**
     * The feed link just made, shown until the page is left: only its hash is stored.
     */
    #[Locked]
    public ?string $newFeedUrl = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Visit::class) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendarFeed')
                ->label('Add to your phone’s calendar')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('gray')
                ->modalHeading('Visits in your calendar app')
                ->modalContent(fn (): View => view('filament.schedule.calendar-feed', ['user' => $this->user(), 'url' => $this->newFeedUrl]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->extraModalFooterActions(fn (): array => [
                    Action::make('makeFeedLink')
                        ->label(filled($this->user()->calendar_feed_token) ? 'Make a new link' : 'Make my link')
                        ->requiresConfirmation(filled($this->user()->calendar_feed_token))
                        ->modalHeading('Make a new link?')
                        ->modalDescription('The old link stops working, so a calendar using it stops getting visits until you add the new one.')
                        ->action(function (): void {
                            $this->newFeedUrl = VisitFeed::issue($this->user());
                        }),
                    Action::make('turnOffFeed')
                        ->label('Turn off the link')
                        ->color('danger')
                        ->visible(filled($this->user()->calendar_feed_token))
                        ->requiresConfirmation()
                        ->modalDescription('Calendars using it stop getting visits.')
                        ->action(function (): void {
                            VisitFeed::revoke($this->user());
                            $this->newFeedUrl = null;
                        }),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [ScheduleCalendar::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
