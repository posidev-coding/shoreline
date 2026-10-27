<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Schemas\VisitForm;
use App\Models\Project;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\Actions\DeleteAction;
use Guava\Calendar\Filament\Actions\EditAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\DateSelectInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\EventResizeInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * The visits calendar on the Schedule page, in the business's time zone. Click a time to book a
 * visit, drag one to move it, or click it to edit it. Done visits stay put.
 */
class ScheduleCalendar extends CalendarWidget
{
    protected static bool $isDiscovered = false;

    protected CalendarViewType $calendarView = CalendarViewType::TimeGridWeek;

    protected bool $useFilamentTimezone = true;

    protected ?string $locale = 'en';

    protected string|HtmlString|bool|null $heading = null;

    protected bool $dayMaxEvents = true;

    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'edit';

    protected bool $dateClickEnabled = true;

    protected bool $dateSelectEnabled = true;

    protected bool $eventDragEnabled = true;

    protected bool $eventResizeEnabled = true;

    /**
     * vkurko/calendar's own options.
     *
     * @var array<string, mixed>
     */
    protected array $options = [
        'headerToolbar' => ['start' => 'prev,next today', 'center' => 'title', 'end' => 'dayGridMonth,timeGridWeek,listWeek'],
        'buttonText' => ['close' => 'Close', 'today' => 'Today', 'dayGridMonth' => 'Month', 'timeGridWeek' => 'Week', 'listWeek' => 'List'],
        'allDaySlot' => false,
        'nowIndicator' => true,
        'scrollTime' => '07:00:00',
        'height' => '720px',
    ];

    /**
     * A visit as the calendar shows it: confirmed in brand orange, tentative in amber, done in gray.
     */
    /**
     * A phone starts on the week's list, since seven time columns don't fit, with a toolbar
     * that does. Only the starting view: the user agent is a guess, and an iPad says desktop.
     */
    public function getCalendarView(): CalendarViewType
    {
        return self::onPhone() ? CalendarViewType::ListWeek : $this->calendarView;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return self::onPhone()
            ? [...$this->options, 'headerToolbar' => ['start' => 'prev,next', 'center' => 'title', 'end' => 'today'], 'height' => 'auto']
            : $this->options;
    }

    public static function event(Visit $visit): CalendarEvent
    {
        [$background, $text] = match ($visit->status) {
            VisitStatus::Confirmed => ['#ff6b1a', '#0b1f33'],
            VisitStatus::Tentative => ['#fef3c7', '#78350f'],
            VisitStatus::Done => ['#e2e8f0', '#334155'],
        };

        return CalendarEvent::make($visit)
            ->title($visit->type->getLabel().' · '.$visit->project->client->display_name)
            ->start($visit->starts_at)
            ->end($visit->ends_at)
            ->backgroundColor($background)
            ->textColor($text)
            ->editable($visit->status !== VisitStatus::Done);
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    protected function getEvents(FetchInfo $info): Collection
    {
        return $this->visits()
            ->where('ends_at', '>', $info->start)
            ->where('starts_at', '<', $info->end)
            ->get()
            ->map(fn (Visit $visit): CalendarEvent => self::event($visit));
    }

    public function visitSchema(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('project_id')
                    ->label('Job')
                    ->helperText('Open jobs are listed. Search to find a finished one, for a warranty or service visit.')
                    ->searchable()
                    ->options(fn (): array => self::jobOptions(Project::query()->open()))
                    ->getSearchResultsUsing(fn (string $search): array => self::jobOptions(Project::query()
                        ->whereNotIn('stage', [ProjectStage::Lost, ProjectStage::Cancelled])
                        ->where(fn (Builder $query) => $query
                            ->whereLike('title', "%{$search}%")
                            ->orWhereHas('client', fn (Builder $client) => $client->whereLike('display_name', "%{$search}%")))
                        ->limit(50)))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::jobOptions(Project::query()->whereKey($value))[$value] ?? null)
                    ->required()
                    ->visible(fn (?Visit $record): bool => $record === null)
                    ->columnSpanFull(),
                ...VisitForm::components(),
            ]);
    }

    public function createVisitAction(): CreateAction
    {
        return $this->createAction(Visit::class)
            ->label('Schedule a visit')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Schedule a visit')
            ->authorize('create')
            ->mountUsing(function (Schema $schema, ?DateClickInfo $dateClick, ?DateSelectInfo $dateSelect): void {
                [$startsAt, $endsAt] = self::window($dateClick, $dateSelect);

                $schema->fill([
                    'status' => VisitStatus::Tentative->value,
                    'starts_at' => $startsAt?->setTimezone(config('app.timezone'))->toDateTimeString(),
                    'ends_at' => $endsAt?->setTimezone(config('app.timezone'))->toDateTimeString(),
                ]);
            });
    }

    public function editAction(): EditAction
    {
        return EditAction::make()
            ->authorize('update')
            ->modalHeading(fn (Visit $record): string => $record->type->getLabel().' · '.$record->project->client->display_name)
            ->modalDescription(fn (Visit $record): string => $record->project->title.', '.$record->project->property->address())
            ->extraModalFooterActions(fn (Visit $record): array => [
                Action::make('openJob')
                    ->label('Open the job')
                    ->color('gray')
                    ->url(ProjectResource::getUrl('view', ['record' => $record->project_id])),
                $this->deleteAction(),
            ]);
    }

    public function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->authorize('delete')
            ->modalHeading('Delete this visit?')
            ->modalDescription('For a visit that’s called off. It’s removed from the job and the calendar.');
    }

    /**
     * @return array<Action>
     */
    public function getHeaderActions(): array
    {
        return [$this->createVisitAction()];
    }

    protected function onEventDrop(EventDropInfo $info, Model $event): bool
    {
        return $this->reschedule($event, $info->event);
    }

    protected function onEventResize(EventResizeInfo $info, Model $event): bool
    {
        return $this->reschedule($event, $info->event);
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        $this->mountAction('createVisit');
    }

    protected function onDateSelect(DateSelectInfo $info): void
    {
        $this->mountAction('createVisit');
    }

    /**
     * Resolves the clicked or dragged visit with what its modal shows, and never one on a trashed job.
     *
     * @return Builder<Model>
     */
    protected function getEloquentQuery(string $model): Builder
    {
        /** @var Builder<Model> */
        return $model === Visit::class ? $this->visits() : parent::getEloquentQuery($model);
    }

    /**
     * @return Builder<Visit>
     */
    private function visits(): Builder
    {
        return Visit::query()->withoutTrashedProjects()->with(['project.client', 'project.property']);
    }

    /**
     * Jobs as "Title (Client)", keyed by id.
     *
     * @param  Builder<Project>  $query
     * @return array<int, string>
     */
    private static function jobOptions(Builder $query): array
    {
        return $query->with('client')->orderBy('title')->get()
            ->mapWithKeys(fn (Project $project): array => [$project->getKey() => "{$project->title} ({$project->client->display_name})"])
            ->all();
    }

    private static function onPhone(): bool
    {
        return str_contains((string) request()->userAgent(), 'Mobile');
    }

    /**
     * The calendar hands back times in the business's zone; they're stored in UTC.
     */
    private function reschedule(Model $visit, CalendarEvent $moved): bool
    {
        if (! $visit instanceof Visit || $visit->status === VisitStatus::Done || ! (auth()->user()?->can('update', $visit) ?? false)) {
            return false;
        }

        $visit->update([
            'starts_at' => $moved->getStart()->clone()->setTimezone(config('app.timezone')),
            'ends_at' => $moved->getEnd()->clone()->setTimezone(config('app.timezone')),
        ]);

        Notification::make()
            ->title('Moved to '.$visit->window())
            ->body('Let '.$visit->project->client->display_name.' know about the new time.')
            ->success()
            ->send();

        return true;
    }

    /**
     * A selected range as it is, or two hours from a clicked time. A day clicked on the month
     * view starts at 8 AM.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function window(?DateClickInfo $dateClick, ?DateSelectInfo $dateSelect): array
    {
        $startsAt = match (true) {
            $dateSelect !== null => $dateSelect->allDay ? $dateSelect->start->setTime(8, 0) : $dateSelect->start,
            $dateClick !== null => $dateClick->allDay ? $dateClick->date->setTime(8, 0) : $dateClick->date,
            default => null,
        };

        $endsAt = match (true) {
            $dateSelect !== null && ! $dateSelect->allDay => $dateSelect->end,
            $startsAt instanceof CarbonInterface => $startsAt->addHours(2),
            default => null,
        };

        return [$startsAt, $endsAt];
    }
}
