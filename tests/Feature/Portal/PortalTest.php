<?php

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Support\PortalAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('shows the updates shared with the client, without who wrote them', function () {
    $project = jobFor($this->property, 'Our new door');
    $author = User::factory()->owner()->create(['name' => 'Owen Owner']);
    ProjectUpdate::factory()->for($project)->create(['user_id' => $author->getKey(), 'body' => "Parts ordered.\nThey arrive Thursday.", 'is_visible_to_client' => true]);
    ProjectUpdate::factory()->for($project)->create(['body' => 'Client seems price-sensitive', 'is_visible_to_client' => false]);

    $this->actingAs($this->user)
        ->get(route('portal.projects.show', $project->ulid))
        ->assertSee('Updates')
        ->assertSee("Parts ordered.\nThey arrive Thursday.")
        ->assertDontSee(['Client seems price-sensitive', 'Owen Owner']);
});

it('shows only the photos and documents marked for the portal', function () {
    Storage::fake('private');
    $project = jobFor($this->property, 'Our new door');
    $add = fn (UploadedFile $file, string $collection, ?bool $inPortal) => $project
        ->addMedia($file)
        ->withCustomProperties($inPortal === null ? [] : [Project::IN_PORTAL => $inPortal])
        ->toMediaCollection($collection);

    $shared = $add(UploadedFile::fake()->image('shared.jpg'), 'photos', true);
    $add(UploadedFile::fake()->image('hidden.jpg'), 'photos', false);
    $add(UploadedFile::fake()->image('from-before.jpg'), 'photos', null);
    $add(UploadedFile::fake()->create('Clopay warranty.pdf', 40, 'application/pdf'), 'documents', true);
    $add(UploadedFile::fake()->create('Supplier invoice.pdf', 40, 'application/pdf'), 'documents', null);

    $this->actingAs($this->user)
        ->get(route('portal.projects.show', $project->ulid))
        ->assertViewHas('photos', fn ($photos): bool => $photos->modelKeys() === [$shared->getKey()])
        ->assertSee(['Photos', 'Clopay warranty', 'PDF'])
        ->assertDontSee('Supplier invoice');
});

it('leaves out sections with nothing shared', function () {
    $project = jobFor($this->property, 'Our new door');
    ProjectUpdate::factory()->for($project)->create(['is_visible_to_client' => false]);

    $this->actingAs($this->user)
        ->get(route('portal.projects.show', $project->ulid))
        ->assertOk()
        ->assertDontSee(['id="updates"', 'id="photos"', 'id="documents"'], false);
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
