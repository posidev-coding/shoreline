<?php

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Filament\Pages\Schedule;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Support\VisitFeed;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->withMfa()->create();
});

/**
 * The feed's path, from the full URL a link is issued as.
 */
function feedPath(string $url): string
{
    return (string) parse_url($url, PHP_URL_PATH);
}

it('serves the visits as a private, uncached calendar without starting a session', function () {
    $property = Property::factory()->vacationRental()->create(['street' => '8 Ocean Dr', 'city' => 'Emerald Isle', 'zip' => '28594']);
    $visit = Visit::factory()->create([
        'project_id' => $property->projects()->create(['title' => 'Wind-rated door install'])->getKey(),
        'starts_at' => '2026-11-03 13:00:00',
        'ends_at' => '2026-11-03 15:00:00',
        'notes' => 'Bring the 16x7 struts',
    ]);

    $response = $this->get(feedPath(VisitFeed::issue($this->owner)));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    $body = $response->getContent();
    expect($body)
        ->toStartWith("BEGIN:VCALENDAR\r\n")
        ->toContain("UID:visit-{$visit->getKey()}@")
        ->toContain("DTSTART:20261103T130000Z\r\n")
        ->toContain('SUMMARY:Install · '.$visit->project->client->display_name.' (tentative)')
        ->toContain("STATUS:TENTATIVE\r\n")
        ->toContain('LOCATION:8 Ocean Dr\, Emerald Isle\, NC 28594')
        ->toContain('Bring the 16x7 struts')
        // The lockbox code on the rental never leaves the database.
        ->not->toContain('Lockbox')
        ->not->toContain('4821');
    expect($this->owner->refresh()->calendar_feed_read_at)->not->toBeNull();
});

it('leaves out visits on deleted jobs and long past', function () {
    $trashed = Visit::factory()->create();
    $trashed->project->delete();
    $old = Visit::factory()->create(['starts_at' => now()->subDays(VisitFeed::DAYS_BACK + 2), 'ends_at' => now()->subDays(VisitFeed::DAYS_BACK + 2)->addHour()]);
    $done = Visit::factory()->create(['status' => VisitStatus::Done, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

    $body = $this->get(feedPath(VisitFeed::issue($this->owner)))->getContent();

    expect($body)
        ->not->toContain("UID:visit-{$trashed->getKey()}@")
        ->not->toContain("UID:visit-{$old->getKey()}@")
        ->toContain("UID:visit-{$done->getKey()}@");
});

it('stops serving a link once it’s replaced or turned off', function () {
    $first = feedPath(VisitFeed::issue($this->owner));
    $second = feedPath(VisitFeed::issue($this->owner));

    $this->get($first)->assertNotFound();
    $this->get($second)->assertOk();

    VisitFeed::revoke($this->owner);

    $this->get($second)->assertNotFound();
});

it('stops serving the link of someone who can’t use the admin anymore', function () {
    $path = feedPath(VisitFeed::issue($this->owner));
    $this->owner->forceFill(['role' => UserRole::Client])->save();

    $this->get($path)->assertNotFound();
});

it('answers an unknown link with a plain 404', function () {
    $this->get('/feeds/visits/'.str_repeat('a', 48).'.ics')->assertNotFound();
});

it('stores only a hash of the link, and shows the link once on the Schedule', function () {
    $this->actingAs($this->owner);

    $component = Livewire::test(Schedule::class)
        ->callAction([TestAction::make('calendarFeed'), TestAction::make('makeFeedLink')]);

    $url = $component->get('newFeedUrl');
    $token = str(feedPath($url))->after('/feeds/visits/')->before('.ics')->toString();

    expect($this->owner->refresh()->calendar_feed_token)->toBe(hash('sha256', $token))
        ->and($this->owner->toArray())->not->toHaveKey('calendar_feed_token');

    Livewire::test(Schedule::class)->callAction([TestAction::make('calendarFeed'), TestAction::make('turnOffFeed')]);

    expect($this->owner->refresh()->calendar_feed_token)->toBeNull();
});
