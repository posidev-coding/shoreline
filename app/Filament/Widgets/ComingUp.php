<?php

namespace App\Filament\Widgets;

use App\Enums\VisitStatus;
use App\Filament\Pages\Schedule;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Visit;
use App\Support\BusinessCalendar;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Today's visits and the week ahead, by day: the schedule as it stands, which the phone's
 * calendar may not have caught up with yet.
 */
class ComingUp extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * Days shown, today included.
     */
    public const int DAYS = 7;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Visit::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $timezone = config('app.business_timezone');

        return $table
            ->heading('Coming up')
            ->query(Visit::query()
                ->withoutTrashedProjects()
                ->with(['project.client', 'project.property'])
                ->where('starts_at', '>=', BusinessCalendar::today()->utc())
                ->where('starts_at', '<', BusinessCalendar::today()->addDays(self::DAYS)->utc()))
            ->defaultSort('starts_at')
            ->defaultGroup(Group::make('starts_at')
                ->label('Day')
                ->titlePrefixedWithLabel(false)
                ->getKeyFromRecordUsing(fn (Visit $record): string => $record->starts_at->clone()->setTimezone($timezone)->toDateString())
                ->getTitleFromRecordUsing(fn (Visit $record): string => self::day($record)))
            ->groupingSettingsHidden()
            ->paginated(false)
            ->recordUrl(fn (Visit $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id]))
            ->columns([
                Split::make([
                    TextColumn::make('starts_at')
                        ->label('When')
                        ->formatStateUsing(fn (Visit $record): string => $record->times())
                        ->weight('semibold')
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('project.client.display_name')
                            ->label('Client')
                            ->formatStateUsing(fn (Visit $record): string => $record->type->getLabel().' · '.$record->project->client->display_name)
                            ->weight('semibold'),
                        TextColumn::make('project.title')
                            ->label('Job')
                            ->color('gray'),
                    ]),
                    TextColumn::make('project.property.street')
                        ->label('Address')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->formatStateUsing(fn (Visit $record): string => $record->project->property->street.', '.$record->project->property->city)
                        ->tooltip('Directions')
                        ->url(fn (Visit $record): string => 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($record->project->property->address()), shouldOpenInNewTab: true),
                    TextColumn::make('status')
                        ->badge()
                        ->grow(false),
                ])->from('md'),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Mark confirmed')
                    ->icon(Heroicon::OutlinedCheck)
                    ->authorize('update')
                    ->visible(fn (Visit $record): bool => $record->status === VisitStatus::Tentative)
                    ->action(fn (Visit $record) => $record->update(['status' => VisitStatus::Confirmed])),
                Action::make('done')
                    ->label('Mark done')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (Visit $record): bool => $record->status !== VisitStatus::Done && $record->starts_at->isPast())
                    ->action(fn (Visit $record) => $record->update(['status' => VisitStatus::Done])),
            ])
            ->headerActions([
                Action::make('schedule')
                    ->label('Open the schedule')
                    ->link()
                    ->url(Schedule::getUrl()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('Nothing scheduled this week')
            ->emptyStateDescription('Visits booked on a job or on the Schedule show up here.');
    }

    /**
     * "Today", "Tomorrow", or "Thursday, Oct 30".
     */
    private static function day(Visit $visit): string
    {
        $day = $visit->starts_at->clone()->setTimezone(config('app.business_timezone'))->startOfDay();

        return match (true) {
            $day->equalTo(BusinessCalendar::today()) => 'Today',
            $day->equalTo(BusinessCalendar::today()->addDay()) => 'Tomorrow',
            default => $day->format('l, M j'),
        };
    }
}
