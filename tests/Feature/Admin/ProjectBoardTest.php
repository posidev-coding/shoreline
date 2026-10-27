<?php

use App\Enums\ProjectStage;
use App\Filament\Pages\ProjectBoard;
use App\Models\Part;
use App\Models\Project;
use App\Models\User;
use App\Support\Stock;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('shows open jobs and the ones completed lately', function () {
    Project::factory()->stage(ProjectStage::Scheduled)->create(['title' => 'Open job']);
    Project::factory()->stage(ProjectStage::OnHold)->create(['title' => 'Paused job']);
    Project::factory()->stage(ProjectStage::Completed)->create(['title' => 'Finished this week']);
    $old = Project::factory()->stage(ProjectStage::Completed)->create(['title' => 'Finished last month']);
    $old->forceFill(['completed_at' => now()->subDays(ProjectBoard::COMPLETED_DAYS + 1)])->saveQuietly();
    Project::factory()->stage(ProjectStage::Lost)->create(['lost_reason' => 'Went with another company', 'title' => 'Lost job']);

    Livewire::test(ProjectBoard::class)
        ->assertSee(['Open job', 'Paused job', 'Finished this week'])
        ->assertDontSee(['Finished last month', 'Lost job']);
});

it('moves a job to the stage it’s dropped on, without a history entry for its place', function () {
    $project = Project::factory()->stage(ProjectStage::Approved)->create();

    Livewire::test(ProjectBoard::class)
        ->call('moveCard', (string) $project->getKey(), ProjectStage::Scheduled->value)
        ->assertReturned(true);

    expect($project->refresh()->stage)->toBe(ProjectStage::Scheduled)
        ->and($project->activitiesAsSubject()->latest('id')->first()->attribute_changes['attributes'])
        ->toHaveKey('stage')
        ->not->toHaveKey('board_position');
});

it('won’t drop a job on a stage the board has no column for', function () {
    $project = Project::factory()->stage(ProjectStage::Approved)->create();

    Livewire::test(ProjectBoard::class)
        ->call('moveCard', (string) $project->getKey(), ProjectStage::Lost->value)
        ->assertReturned(false)
        ->assertNotified();

    expect($project->refresh()->stage)->toBe(ProjectStage::Approved);
});

it('puts a job that changes stage elsewhere at the end of its new column', function () {
    $first = Project::factory()->stage(ProjectStage::Scheduled)->create();
    $second = Project::factory()->stage(ProjectStage::Scheduled)->create();
    $moved = Project::factory()->stage(ProjectStage::Approved)->create();

    $moved->update(['stage' => ProjectStage::Scheduled]);

    expect((float) $first->board_position)->toBeLessThan((float) $second->board_position)
        ->and((float) $moved->refresh()->board_position)->toBeGreaterThan((float) $second->board_position);
});

it('uses the stocked parts and offers to record the equipment when a job is completed on the board', function () {
    $spring = Part::factory()->spring()->create();
    app(Stock::class)->adjustTo($spring, '3');
    $project = Project::factory()->stage(ProjectStage::InProgress)->create(['title' => 'Spring job']);
    $project->lineItems()->create(['part_id' => $spring->getKey(), 'description' => $spring->name, 'quantity' => '2', 'unit_price' => '89']);

    Livewire::test(ProjectBoard::class)
        ->call('moveCard', (string) $project->getKey(), ProjectStage::Completed->value)
        ->assertNotified('Spring job is completed');

    expect($project->refresh()->completed_at)->not->toBeNull()
        ->and((string) $spring->refresh()->on_hand)->toBe('1.00');
});

it('changes a job to a stage off the board from its card, asking why it was lost', function () {
    $project = Project::factory()->stage(ProjectStage::ProposalSent)->create();

    // Mounted the way a card's menu does it, with the card's key as context: the board finds
    // the record from the mounted action, which Filament's callAction() checks for too early.
    Livewire::test(ProjectBoard::class)
        ->call('mountAction', 'changeStage', [], ['recordKey' => (string) $project->getKey()])
        ->set('mountedActions.0.data.stage', ProjectStage::Lost->value)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.lost_reason' => 'required'])
        ->set('mountedActions.0.data.lost_reason', 'Too expensive')
        ->call('callMountedAction')
        ->assertHasNoErrors();

    expect($project->refresh())
        ->stage->toBe(ProjectStage::Lost)
        ->lost_reason->toBe('Too expensive');
});

it('is only for admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ProjectBoard::getUrl())->assertForbidden();
});
