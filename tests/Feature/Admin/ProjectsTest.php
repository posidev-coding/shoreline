<?php

use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectHistory;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\LineItemsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Projects\RelationManagers\UpdatesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\VisitsRelationManager;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Models\ProjectTask;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
});

it('opens on the projects still in play', function () {
    $open = Project::factory()->stage(ProjectStage::Scheduled)->create();
    $done = Project::factory()->stage(ProjectStage::Completed)->create();
    $lost = Project::factory()->stage(ProjectStage::Lost)->create();

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$done, $lost]);
});

it('starts a project at one of the client’s properties', function () {
    $property = Property::factory()->create();
    Property::factory()->create();

    Livewire::test(CreateProject::class)
        ->fillForm(['client_id' => $property->client_id])
        ->assertFormFieldExists('property_id', fn ($field): bool => array_keys($field->getOptions()) === [$property->getKey()])
        ->fillForm(['property_id' => $property->getKey(), 'title' => 'New double door'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Project::query()->sole())
        ->client_id->toBe($property->client_id)
        ->stage->toBe(ProjectStage::Estimating);
});

it('starts a project from a client’s page', function () {
    $property = Property::factory()->create();

    Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $property->client, 'pageClass' => ViewClient::class])
        ->callAction(TestAction::make('create')->table(), data: ['property_id' => $property->getKey(), 'title' => 'Opener install'])
        ->assertHasNoFormErrors();

    expect($property->client->projects()->sole()->title)->toBe('Opener install');
});

it('takes the client from the property, so the two can’t disagree', function () {
    $project = Project::factory()->create();
    $otherProperty = Property::factory()->for(Client::factory())->create();

    $project->update(['property_id' => $otherProperty->getKey()]);

    expect($project->refresh()->client_id)->toBe($otherProperty->client_id);
});

it('moves through stages, noting when, and asks why one was lost', function () {
    $project = Project::factory()->create();
    Carbon::setTestNow('2026-11-02 14:00');

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->callAction('changeStage', data: ['stage' => ProjectStage::Lost->value])
        ->assertHasFormErrors(['lost_reason' => 'required']);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->callAction('changeStage', data: ['stage' => ProjectStage::Lost->value, 'lost_reason' => 'Went with a cheaper quote'])
        ->assertHasNoFormErrors();

    expect($project->refresh())
        ->stage->toBe(ProjectStage::Lost)
        ->lost_reason->toBe('Went with a cheaper quote')
        ->stage_changed_at->toDateTimeString()->toBe('2026-11-02 14:00:00');

    $project->update(['stage' => ProjectStage::Completed]);
    expect($project->refresh())
        ->lost_reason->toBeNull()
        ->completed_at->not->toBeNull();

    $project->update(['stage' => ProjectStage::InProgress]);
    expect($project->refresh()->completed_at)->toBeNull();
});

it('prices each line to the cent, rounding half up, and totals them before tax', function () {
    $project = Project::factory()->create();
    ProjectLineItem::factory()->for($project)->create(['quantity' => '3', 'unit_price' => '0.1250']);
    ProjectLineItem::factory()->for($project)->create(['quantity' => '1', 'unit_price' => '189.99']);

    expect((string) $project->lineItems->first()->amount()->getAmount())->toBe('0.38')
        ->and((string) $project->subtotal()->getAmount())->toBe('190.37');

    Livewire::test(LineItemsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->assertSee('Subtotal $190.37 before tax')
        ->assertSee('$0.125');
});

it('adds line items in order', function () {
    $project = Project::factory()->create();
    ProjectLineItem::factory()->for($project)->create(['sort_order' => 4]);

    Livewire::test(LineItemsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->callAction(TestAction::make('create')->table(), data: ['description' => 'Labor', 'quantity' => 2, 'unit_price' => 85])
        ->assertHasNoFormErrors();

    expect(ProjectLineItem::query()->latest('id')->first())
        ->description->toBe('Labor')
        ->sort_order->toBe(5)
        ->is_taxable->toBeTrue();
});

it('schedules a visit in local time and shows its arrival window', function () {
    $project = Project::factory()->create();

    Livewire::test(VisitsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->callAction(TestAction::make('create')->table(), data: [
            'type' => VisitType::Install->value,
            'starts_at' => '2026-11-03 08:00',
            'ends_at' => '2026-11-03 10:00',
        ])
        ->assertHasNoFormErrors();

    $visit = Visit::query()->sole();

    // 8 AM Eastern is 1 PM UTC in November.
    expect($visit->starts_at->toDateTimeString())->toBe('2026-11-03 13:00:00')
        ->and($visit->status)->toBe(VisitStatus::Tentative)
        ->and($visit->window())->toBe('Tue, Nov 3 · 8–10 AM');

    $visit->update(['ends_at' => $visit->starts_at->clone()->addHours(5)->addMinutes(30)]);
    expect($visit->window())->toBe('Tue, Nov 3 · 8 AM–1:30 PM');
});

it('won’t take a window that ends before it starts', function () {
    $project = Project::factory()->create();

    Livewire::test(VisitsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->callAction(TestAction::make('create')->table(), data: [
            'type' => VisitType::SiteMeasure->value,
            'starts_at' => '2026-11-03 10:00',
            'ends_at' => '2026-11-03 08:00',
        ])
        ->assertHasFormErrors(['ends_at' => 'after']);
});

it('ticks off tasks', function () {
    $task = ProjectTask::factory()->create();

    Livewire::test(TasksRelationManager::class, ['ownerRecord' => $task->project, 'pageClass' => ViewProject::class])
        ->call('updateTableColumnState', 'is_done', (string) $task->getKey(), true);

    expect($task->refresh()->completed_at)->not->toBeNull();
});

it('signs timeline updates with who wrote them', function () {
    $project = Project::factory()->create();

    Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->callAction(TestAction::make('create')->table(), data: ['body' => 'Ordered the door, two weeks out.'])
        ->assertHasNoFormErrors();

    expect($project->updates()->sole())
        ->user_id->toBe($this->owner->getKey())
        ->is_visible_to_client->toBeFalse();
});

it('shows a project with its client, property, and history', function () {
    $project = Project::factory()->for(Property::factory()->vacationRental())->create(['title' => 'Replace broken torsion spring']);
    $project->update(['stage' => ProjectStage::Scheduled]);

    $this->get(ProjectResource::getUrl('view', ['record' => $project]))
        ->assertOk()
        ->assertSee('Replace broken torsion spring')
        ->assertSee($project->client->display_name)
        ->assertSee('Lockbox 4821');

    // Labels, not the stored values, and the property's address, not its id.
    Livewire::test(ProjectHistory::class, ['record' => $project->getKey()])
        ->assertOk()
        ->assertSeeInOrder(['Estimating', 'Scheduled'])
        ->assertSee($project->property->address())
        ->assertDontSee('scheduled');
});

it('still shows a trashed project’s client', function () {
    $project = Project::factory()->create();
    $project->client->delete();

    $this->get(ProjectResource::getUrl('view', ['record' => $project]))
        ->assertOk()
        ->assertSee($project->client->display_name);
});
