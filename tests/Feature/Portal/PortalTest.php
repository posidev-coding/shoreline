<?php

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Support\PortalAccess;

beforeEach(function () {
    $this->client = Client::factory()->create(['display_name' => 'Jane Homeowner']);
    $this->user = User::factory()->portalUserOf($this->client)->create();
    $this->property = Property::factory()->for($this->client)->create(['street' => '12 Front St']);
});

function jobFor(Property $property, string $title, ProjectStage $stage = ProjectStage::Scheduled): Project
{
    return Project::factory()->for($property)->stage($stage)->create(['title' => $title]);
}

it('sends a guest to the sign-in page', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
});

it('shows a client their own jobs, and never another client’s', function () {
    jobFor($this->property, 'Our new door');
    jobFor(Property::factory()->create(), 'Someone else’s door');

    $this->actingAs($this->user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Our new door')
        ->assertDontSee('Someone else’s door');
});

it('answers a job that isn’t theirs with a 404, the same as one that doesn’t exist', function () {
    $mine = jobFor($this->property, 'Our new door');
    $theirs = jobFor(Property::factory()->create(), 'Someone else’s door');

    $this->actingAs($this->user);
    $this->get(route('portal.projects.show', $mine->ulid))->assertOk()->assertSee('12 Front St');
    $this->get(route('portal.projects.show', $theirs->ulid))->assertNotFound();
    $this->get(route('portal.projects.show', '01jzzzzzzzzzzzzzzzzzzzzzzz'))->assertNotFound();
    $this->get('/portal/projects/'.$mine->getKey())->assertNotFound();
});

it('leaves out jobs that never happened and deleted ones', function () {
    $lost = jobFor($this->property, 'Lost door', ProjectStage::Lost);
    $cancelled = jobFor($this->property, 'Cancelled door', ProjectStage::Cancelled);
    $deleted = jobFor($this->property, 'Deleted door');
    $deleted->delete();
    jobFor($this->property, 'Finished door', ProjectStage::Completed);

    $this->actingAs($this->user)
        ->get(route('portal.dashboard'))
        ->assertSee('Finished door')
        ->assertDontSee(['Lost door', 'Cancelled door', 'Deleted door']);

    foreach ([$lost, $cancelled, $deleted] as $project) {
        $this->get(route('portal.projects.show', $project->ulid))->assertNotFound();
    }
});

it('shows someone with two clients both, with whose each job is', function () {
    $company = Client::factory()->create(['display_name' => 'Beach Rentals LLC']);
    $company->portalUsers()->attach($this->user->getKey(), ['client_role' => ClientUserRole::Manager->value]);
    jobFor($this->property, 'Home door');
    jobFor(Property::factory()->for($company)->create(), 'Rental opener');

    $this->actingAs($this->user)
        ->get(route('portal.dashboard'))
        ->assertSee(['Home door', 'Rental opener', 'Jane Homeowner', 'Beach Rentals LLC']);
});

it('shows visits without the owner’s notes, and the next one first', function () {
    $project = jobFor($this->property, 'Our new door');
    Visit::factory()->for($project)->create(['status' => VisitStatus::Tentative, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(2), 'notes' => 'Bring the big ladder']);
    Visit::factory()->for($project)->create(['status' => VisitStatus::Done, 'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour()]);

    $this->actingAs($this->user);
    $this->get(route('portal.dashboard'))->assertSee('Next visit')->assertSee('to be confirmed');
    $this->get(route('portal.projects.show', $project->ulid))
        ->assertSee(['We’ll confirm the time with you.', 'Earlier'])
        ->assertDontSee('Bring the big ladder');
});

it('signs someone out on their next page once their access is removed', function () {
    PortalAccess::remove($this->client, $this->user);
    $remaining = User::factory()->portalUserOf($this->client)->create();
    $other = Client::factory()->create();
    $other->portalUsers()->attach($remaining->getKey(), ['client_role' => ClientUserRole::Viewer->value]);
    PortalAccess::remove($other, $remaining);

    // The first had no other client, so they're gone; the second still has this one.
    expect(User::query()->find($this->user->getKey()))->toBeNull()
        ->and($remaining->refresh()->clients()->pluck('clients.id')->all())->toBe([$this->client->getKey()]);

    $leaving = User::factory()->portalUserOf($other)->create();
    $this->actingAs($leaving);
    $other->delete();

    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'))->assertSessionHas('status');
    $this->assertGuest();
});

it('turns an admin away, since they look through a client’s eyes from the admin', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

it('never lets an edge cache keep a portal page', function () {
    $response = $this->actingAs($this->user)->get(route('portal.dashboard'));

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});
