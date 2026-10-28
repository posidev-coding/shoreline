<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\ProjectBoard;
use App\Filament\Pages\Schedule;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Parts\PartResource;
use App\Models\Lead;
use App\Models\Part;
use App\Models\Project;
use App\Models\Visit;
use App\Support\BusinessCalendar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The week at a glance, counted Monday to Sunday in the business's time zone. Money arrives
 * with QuickBooks (M7).
 */
class WorkStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    /**
     * No polling: a dashboard left open would keep the app from sleeping.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Project::class) ?? false;
    }

    protected function getStats(): array
    {
        $weekStart = BusinessCalendar::weekStart()->utc();
        $weekEnd = BusinessCalendar::weekStart()->addWeek()->utc();

        $newRequests = Lead::query()->where('created_at', '>=', $weekStart)->where('status', '!=', LeadStatus::Spam)->count();
        $awaitingReply = Lead::query()->where('status', LeadStatus::New)->count();

        $openJobs = Project::query()->open()->count();
        $completed = Project::query()->where('completed_at', '>=', $weekStart)->count();

        $visits = Visit::query()->withoutTrashedProjects()->where('starts_at', '>=', $weekStart)->where('starts_at', '<', $weekEnd);
        $tentative = (clone $visits)->where('status', VisitStatus::Tentative)->count();

        $lowStock = Part::query()->active()->lowStock()->count();

        return [
            Stat::make('Quote requests this week', $newRequests)
                ->description($awaitingReply === 1 ? '1 waiting for a reply' : "{$awaitingReply} waiting for a reply")
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($awaitingReply > 0 ? 'warning' : 'gray')
                ->url(LeadResource::getUrl()),
            Stat::make('Open jobs', $openJobs)
                ->description("{$completed} completed this week")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->url(ProjectBoard::getUrl()),
            Stat::make('Visits this week', $visits->count())
                ->description($tentative > 0 ? "{$tentative} still tentative" : 'All confirmed')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($tentative > 0 ? 'warning' : 'gray')
                ->url(Schedule::getUrl()),
            Stat::make('Parts low on stock', $lowStock)
                ->description($lowStock > 0 ? 'Time to reorder' : 'Nothing to reorder')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color($lowStock > 0 ? 'danger' : 'gray')
                ->url(PartResource::getUrl('index', $lowStock > 0 ? ['filters' => ['low_stock' => ['isActive' => true]]] : [])),
        ];
    }
}
