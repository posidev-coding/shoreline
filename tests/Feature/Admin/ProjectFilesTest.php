<?php

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\FilesRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    $this->actingAs(User::factory()->owner()->withMfa()->create());
    $this->project = Project::factory()->create();
});

function filesTab(Project $project): mixed
{
    return Livewire::test(FilesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class]);
}

it('shows new photos in the portal, keeps new documents private, and keeps a document’s name', function () {
    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->fillForm([
            'photos' => [UploadedFile::fake()->image('door.jpg', 800, 600)],
            'documents' => [UploadedFile::fake()->create('Clopay warranty.pdf', 40, 'application/pdf')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $photo = $this->project->getFirstMedia('photos');
    $document = $this->project->getFirstMedia('documents');

    expect(Project::isInPortal($photo))->toBeTrue()
        ->and(Project::isInPortal($document))->toBeFalse()
        ->and($document->file_name)->toBe('Clopay-warranty.pdf');
});

it('leaves a photo taken before the portal showed any private', function () {
    $photo = $this->project->addMedia(UploadedFile::fake()->image('before.jpg'))->toMediaCollection('photos');

    expect(Project::isInPortal($photo))->toBeFalse()
        ->and($this->project->sharedMedia('photos'))->toBeEmpty();
});

it('editing the job again leaves each file’s choice alone', function () {
    $hidden = $this->project->addMedia(UploadedFile::fake()->image('hidden.jpg'))
        ->withCustomProperties([Project::IN_PORTAL => false])
        ->toMediaCollection('photos');

    Livewire::test(EditProject::class, ['record' => $this->project->getRouteKey()])
        ->fillForm(['title' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Project::isInPortal($hidden->refresh()))->toBeFalse();
});

it('shows and hides a file in the portal from the Files tab', function () {
    $photo = $this->project->addMedia(UploadedFile::fake()->image('door.jpg'))->toMediaCollection('photos');
    $document = $this->project->addMedia(UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    filesTab($this->project)
        ->assertCanSeeTableRecords([$photo, $document])
        ->call('updateTableColumnState', 'in_portal', (string) $document->getKey(), true);

    expect(Project::isInPortal($document->refresh()))->toBeTrue();

    filesTab($this->project)
        ->selectTableRecords([$photo->getKey(), $document->getKey()])
        ->callAction(TestAction::make('show')->table()->bulk());
    expect($this->project->refresh()->sharedMedia('photos'))->toHaveCount(1);

    filesTab($this->project)
        ->selectTableRecords([$photo->getKey(), $document->getKey()])
        ->callAction(TestAction::make('hide')->table()->bulk());
    expect($this->project->refresh()->sharedMedia('photos'))->toBeEmpty()
        ->and($this->project->sharedMedia('documents'))->toBeEmpty();
});

it('deletes a file from the Files tab', function () {
    $document = $this->project->addMedia(UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    filesTab($this->project)->callAction(TestAction::make('delete')->table($document));

    expect($this->project->refresh()->getMedia('documents'))->toBeEmpty();
    Storage::disk('private')->assertMissing($document->getPathRelativeToRoot());
});
