<?php

use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Filament\Widgets\ComingUp;
use App\Filament\Widgets\LowStockParts;
use App\Filament\Widgets\NewQuoteRequests;
use App\Filament\Widgets\PartsForJobs;
use App\Filament\Widgets\WorkStats;
use App\Models\Lead;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Models\User;
use App\Models\Visit;
use App\Support\Stock;
use Filament\Actions\Testing\TestAction;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
    // Wednesday afternoon in a week that ends on the first of November, when clocks fall back.
    $this->travelTo(Carbon::parse('2026-10-28 15:00', 'America/New_York'));
});

/**
 * @return array<string, array{value: mixed, description: string|null}>
 */
function stats(): array
{
    $widget = Livewire::test(WorkStats::class)->instance();

    return collect((fn (): array => $this->getStats())->call($widget))
        ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => ['value' => $stat->getValue(), 'description' => (string) $stat->getDescription()]])
        ->all();
}

/**
 * A visit starting at a wall-clock time in Eastern.
 */
function visitAt(string $easternTime, VisitStatus $status = VisitStatus::Confirmed): Visit
{
    $starts = Carbon::parse($easternTime, 'America/New_York');

    return Visit::factory()->create(['status' => $status, 'starts_at' => $starts->clone()->utc(), 'ends_at' => $starts->clone()->addHour()->utc()]);
}

it('counts the week from Monday to Sunday in Eastern time', function () {
    visitAt('2026-10-25 23:00');                          // Sunday night before: last week, though Monday in UTC.
    visitAt('2026-10-26 00:30', VisitStatus::Tentative);  // Monday just after midnight.
    visitAt('2026-11-01 21:00');                          // Sunday night after the clocks fall back: Monday in UTC.
    visitAt('2026-11-02 09:00');                          // Next week.

    expect(stats()['Visits this week'])->toBe(['value' => 2, 'description' => '1 still tentative']);
});

it('counts this week’s quote requests, leaving out spam, and every one still waiting for a reply', function () {
    Lead::factory()->count(2)->create();
    Lead::factory()->spam()->create();
    Lead::factory()->contacted()->create();
    Lead::factory()->create(['created_at' => Carbon::parse('2026-10-25 20:00', 'America/New_York')]);

    expect(stats()['Quote requests this week'])->toBe(['value' => 3, 'description' => '3 waiting for a reply']);
});

it('counts open jobs, and parts at or under their reorder point', function () {
    Project::factory()->stage(ProjectStage::Scheduled)->create();
    Project::factory()->stage(ProjectStage::OnHold)->create();
    Project::factory()->stage(ProjectStage::Completed)->create();
    Project::factory()->stage(ProjectStage::Cancelled)->create();
    $low = Part::factory()->spring()->create();
    app(Stock::class)->adjustTo($low, '2');
    $fine = Part::factory()->spring()->create();
    app(Stock::class)->adjustTo($fine, '5');

    expect(stats())
        ->{'Open jobs'}->toBe(['value' => 2, 'description' => '1 completed this week'])
        ->{'Parts low on stock'}->toBe(['value' => 1, 'description' => 'Time to reorder']);
});

it('lists today’s visits and the week ahead, by day', function () {
    $earlierToday = visitAt('2026-10-28 08:00');
    $tomorrow = visitAt('2026-10-29 09:00', VisitStatus::Tentative);
    $yesterday = visitAt('2026-10-27 09:00');
    $tooFar = visitAt('2026-11-04 09:00');
    $trashed = visitAt('2026-10-30 09:00');
    $trashed->project->delete();

    Livewire::test(ComingUp::class)
        ->assertCanSeeTableRecords([$earlierToday, $tomorrow])
        ->assertCanNotSeeTableRecords([$yesterday, $tooFar, $trashed])
        ->assertSee(['Today', 'Tomorrow', '8–9 AM'])
        ->callAction(TestAction::make('confirm')->table($tomorrow));

    expect($tomorrow->refresh()->status)->toBe(VisitStatus::Confirmed);
});

it('lists new quote requests, low parts, and parts still to come for jobs', function () {
    $waiting = Lead::factory()->create(['name' => 'Waiting Person']);
    $answered = Lead::factory()->contacted()->create(['name' => 'Answered Person']);
    $low = Part::factory()->spring()->create();
    app(Stock::class)->adjustTo($low, '1');
    $plenty = Part::factory()->spring()->create();
    app(Stock::class)->adjustTo($plenty, '9');
    $toOrder = ProjectLineItem::factory()->create(['order_status' => OrderStatus::ToOrder]);
    $onTheWay = ProjectLineItem::factory()->create(['order_status' => OrderStatus::Ordered]);
    $received = ProjectLineItem::factory()->create(['order_status' => OrderStatus::Received]);

    Livewire::test(NewQuoteRequests::class)
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$answered]);
    Livewire::test(LowStockParts::class)
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$plenty]);
    Livewire::test(PartsForJobs::class)
        ->assertCanSeeTableRecords([$toOrder, $onTheWay], inOrder: true)
        ->assertCanNotSeeTableRecords([$received]);

    expect($waiting->status)->toBe(LeadStatus::New);
});

it('opens on the dashboard', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(WorkStats::class)
        ->assertSeeLivewire(ComingUp::class);
});
