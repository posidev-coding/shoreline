<?php

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\ProjectStage;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Properties\Pages\ViewProperty;
use App\Filament\Resources\Properties\RelationManagers\EquipmentRelationManager;
use App\Models\InstalledEquipment;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Models\Property;
use App\Models\User;
use App\Support\EquipmentRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('lists each door with what’s on it underneath', function () {
    $property = Property::factory()->create();
    $garage = InstalledEquipment::factory()->for($property)->create(['size' => '16x7']);
    $shop = InstalledEquipment::factory()->for($property)->create(['size' => '9x7']);
    $garageOpener = InstalledEquipment::factory()->on($garage)->create();
    $shopSprings = InstalledEquipment::factory()->on($shop, EquipmentType::SpringSet)->create();
    $garageSprings = InstalledEquipment::factory()->on($garage, EquipmentType::SpringSet)->create();

    Livewire::test(EquipmentRelationManager::class, ['ownerRecord' => $property, 'pageClass' => ViewProperty::class])
        ->assertCanSeeTableRecords([$garage, $garageOpener, $garageSprings, $shop, $shopSprings], inOrder: true);
});

it('deletes what’s on a door with it, photos included', function () {
    Storage::fake('private');
    $door = InstalledEquipment::factory()->create();
    $door->addMedia(UploadedFile::fake()->image('label.jpg'))->toMediaCollection('label');
    InstalledEquipment::factory()->on($door)->create();

    $door->delete();

    expect(InstalledEquipment::query()->count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('drafts the equipment from a job’s line items, on the new door', function () {
    $project = Project::factory()->create();
    foreach ([Part::factory()->door(), Part::factory()->opener(), Part::factory()->spring(), Part::factory()->spring(), Part::factory()] as $part) {
        ProjectLineItem::factory()->for($project)->create(['part_id' => $part->create()->getKey()]);
    }
    // Billed again as a second line of the same spring.
    ProjectLineItem::factory()->for($project)->create(['part_id' => $project->lineItems()->skip(2)->value('part_id')]);
    ProjectLineItem::factory()->for($project)->create(['description' => 'Labor']);
    $project->update(['stage' => ProjectStage::Completed]);

    $rows = EquipmentRecorder::draft($project);

    // The left and right springs are one set, and the hardware and labor aren't equipment worth tracking.
    expect(array_column($rows, 'type'))->toBe(['door', 'opener', 'spring_set'])
        ->and($rows[1])->toMatchArray(['manufacturer' => 'LiftMaster', 'model' => '87504-267'])
        ->and($rows[1]['warranty_expires_on'])->toBe($project->completed_at->clone()->timezone('America/New_York')->addMonths(60)->toDateString())
        ->and(EquipmentRecorder::suggestedDoor($project, $rows))->toBe('new');
});

it('offers to record what a job installed when it’s completed, and records it', function () {
    Carbon::setTestNow('2026-11-02 15:00');
    $property = Property::factory()->create();
    $door = InstalledEquipment::factory()->for($property)->create();
    $oldOpener = InstalledEquipment::factory()->on($door)->create();
    $project = Project::factory()->for($property)->stage(ProjectStage::InProgress)->create();
    ProjectLineItem::factory()->for($project)->create(['part_id' => Part::factory()->opener()->create()->getKey()]);

    $page = Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->callAction('changeStage', data: ['stage' => ProjectStage::Completed->value])
        ->assertActionMounted('recordEquipment')
        ->assertActionDataSet(['door' => (string) $door->getKey()]);

    // The one opener already on that door is the one this job replaced.
    expect(collect($page->get('mountedActions.0.data.rows'))->first())
        ->toMatchArray(['type' => 'opener', 'replaces' => (string) $oldOpener->getKey()]);

    $page->callMountedAction()->assertHasNoFormErrors();

    $newOpener = InstalledEquipment::query()->where('installed_by_project_id', $project->getKey())->sole();

    expect($newOpener)
        ->type->toBe(EquipmentType::Opener)
        ->parent_id->toBe($door->getKey())
        ->installed_on->toDateString()->toBe('2026-11-02')
        ->and($oldOpener->refresh())
        ->status->toBe(EquipmentStatus::Replaced)
        ->replaced_by_id->toBe($newOpener->getKey());
});
