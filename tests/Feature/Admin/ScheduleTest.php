<?php

use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Filament\Pages\Schedule;
use App\Filament\Widgets\ScheduleCalendar;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

/**
 * What EventCalendar sends back for a view, here the week of Nov 2 seen from Eastern time.
 *
 * @return array<string, string>
 */
function calendarView(): array
{
    return [
        'type' => 'timeGridWeek', 'title' => 'Nov 2 – 8, 2026',
        'currentStart' => '2026-11-02T05:00:00.000Z', 'currentEnd' => '2026-11-09T05:00:00.000Z',
        'activeStart' => '2026-11-02T05:00:00.000Z', 'activeEnd' => '2026-11-09T05:00:00.000Z',
    ];
}

/**
 * The drop EventCalendar reports when a visit is dragged to a new wall-clock time. It ignores
 * time zones, so the browser's own offset turns that wall-clock time into the UTC it sends.
 *
 * @return array<string, mixed>
 */
function droppedVisit(Visit $visit, string $wallClockStart, string $wallClockEnd, int $browserOffset): array
{
    $event = ScheduleCalendar::event($visit->load('project.client'))->toCalendarObject($browserOffset, true);
    $toUtc = fn (string $wallClock): string => now()->parse($wallClock, 'UTC')->subMinutes($browserOffset)->format('Y-m-d\TH:i:s.v\Z');

    return [
        'event' => [...$event, 'start' => $toUtc($wallClockStart), 'end' => $toUtc($wallClockEnd)],
        'oldEvent' => $event,
        'view' => calendarView(),
        'tzOffset' => $browserOffset,
    ];
}

it('shows the visits in view, in Eastern time, and none from deleted jobs', function () {
    // 8–10 AM Eastern on Tuesday, Nov 3.
    $shown = Visit::factory()->confirmed()->create(['starts_at' => '2026-11-03 13:00:00', 'ends_at' => '2026-11-03 15:00:00']);
    Visit::factory()->create(['starts_at' => '2026-11-12 13:00:00', 'ends_at' => '2026-11-12 15:00:00']);
    $trashed = Visit::factory()->create(['starts_at' => '2026-11-04 13:00:00', 'ends_at' => '2026-11-04 15:00:00']);
    $trashed->project->delete();

    $events = Livewire::test(ScheduleCalendar::class)->instance()
        ->getEventsJs(['startStr' => '2026-11-02T00:00:00', 'endStr' => '2026-11-09T00:00:00', 'tzOffset' => -300]);

    expect($events)->toHaveCount(1)
        ->and($events[0]['extendedProps']['key'])->toBe((string) $shown->getKey())
        ->and($events[0]['start'])->toBe('2026-11-03T08:00:00-05:00')
        ->and($events[0]['title'])->toBe('Install · '.$shown->project->client->display_name);
});

it('books a visit for a job, in Eastern time', function () {
    $project = Project::factory()->create();

    Livewire::test(ScheduleCalendar::class)
        ->callAction('createVisit', data: [
            'project_id' => $project->getKey(),
            'type' => VisitType::SiteMeasure->value,
            'status' => VisitStatus::Tentative->value,
            'starts_at' => '2026-11-03 08:00',
            'ends_at' => '2026-11-03 09:00',
        ])
        ->assertHasNoErrors();

    expect(Visit::query()->sole())
        ->project_id->toBe($project->getKey())
        ->starts_at->toDateTimeString()->toBe('2026-11-03 13:00:00');
});

it('books a warranty visit on a finished job, found by searching', function () {
    $finished = Project::factory()->stage(ProjectStage::Completed)->create(['title' => 'Opener install']);

    Livewire::test(ScheduleCalendar::class)
        ->callAction('createVisit', data: [
            'project_id' => $finished->getKey(),
            'type' => VisitType::Warranty->value,
            'status' => VisitStatus::Confirmed->value,
            'starts_at' => '2026-11-03 08:00',
            'ends_at' => '2026-11-03 09:00',
        ])
        ->assertHasNoErrors();

    expect(Visit::query()->sole()->project_id)->toBe($finished->getKey());
});

it('starts a visit booked from a clicked time with a two-hour window there', function () {
    Livewire::test(ScheduleCalendar::class)
        // 9 AM Eastern clicked.
        ->call('onDateClickJs', ['date' => '2026-11-03T14:00:00.000Z', 'allDay' => false, 'view' => calendarView(), 'tzOffset' => -300])
        ->assertActionMounted('createVisit')
        // The picker shows 9–11 AM; its state comes back in UTC.
        ->assertActionDataSet(['starts_at' => '2026-11-03 14:00', 'ends_at' => '2026-11-03 16:00']);
});

it('moves a dragged visit to the same wall-clock time in Eastern, wherever the browser is', function () {
    $visit = Visit::factory()->confirmed()->create(['starts_at' => '2026-11-03 13:00:00', 'ends_at' => '2026-11-03 15:00:00']);

    // Dragged to 1–3 PM in a browser on Pacific time, which shows the calendar's Eastern times as they are.
    Livewire::test(ScheduleCalendar::class)
        ->call('onEventDropJs', droppedVisit($visit, '2026-11-03 13:00', '2026-11-03 15:00', -480))
        ->assertReturned(true)
        ->assertNotified('Moved to Tue, Nov 3 · 1–3 PM');

    expect($visit->refresh())
        ->starts_at->toDateTimeString()->toBe('2026-11-03 18:00:00')
        ->ends_at->toDateTimeString()->toBe('2026-11-03 20:00:00');
});

it('keeps done visits where they are', function () {
    $visit = Visit::factory()->create(['status' => VisitStatus::Done, 'starts_at' => '2026-11-03 13:00:00', 'ends_at' => '2026-11-03 15:00:00']);

    Livewire::test(ScheduleCalendar::class)
        ->call('onEventDropJs', droppedVisit($visit, '2026-11-04 13:00', '2026-11-04 15:00', -300))
        ->assertReturned(false);

    expect($visit->refresh()->starts_at->toDateTimeString())->toBe('2026-11-03 13:00:00');
});

it('refuses a dragged event whose identity was tampered with', function () {
    $visit = Visit::factory()->create(['starts_at' => '2026-11-03 13:00:00', 'ends_at' => '2026-11-03 15:00:00']);
    $other = Visit::factory()->create(['starts_at' => '2026-11-05 13:00:00', 'ends_at' => '2026-11-05 15:00:00']);
    $drop = droppedVisit($visit, '2026-11-04 13:00', '2026-11-04 15:00', -300);
    $drop['event']['extendedProps']['key'] = $other->getKey();

    expect(fn () => Livewire::test(ScheduleCalendar::class)->call('onEventDropJs', $drop))->toThrow(Exception::class);
    expect($other->refresh()->starts_at->toDateTimeString())->toBe('2026-11-05 13:00:00');
});

it('starts a phone on the week’s list', function () {
    $iPhone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    $mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15';

    expect(Livewire::withHeaders(['User-Agent' => $iPhone])->test(ScheduleCalendar::class)->instance()->getCalendarView()->value)->toBe('listWeek')
        ->and(Livewire::withHeaders(['User-Agent' => $mac])->test(ScheduleCalendar::class)->instance()->getCalendarView()->value)->toBe('timeGridWeek');
});

it('is only for admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get(Schedule::getUrl())->assertForbidden();
});
