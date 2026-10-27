<?php

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\PropertiesRelationManager;
use App\Models\Client;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('lists clients with their property and open-project counts', function () {
    $client = Client::factory()->create();
    Project::factory()->for(Property::factory()->for($client))->create();

    Livewire::test(ListClients::class)
        ->assertCanSeeTableRecords([$client])
        ->assertTableColumnStateSet('properties_count', 1, $client)
        ->assertTableColumnStateSet('open_projects_count', 1, $client);
});

it('creates a client, naming them from their name, with the email lowercase and the phone as E.164', function () {
    Livewire::test(CreateClient::class)
        ->fillForm(['first_name' => 'Jane'])
        ->assertSchemaStateSet(['display_name' => 'Jane'])
        ->fillForm(['last_name' => 'Smith'])
        ->assertSchemaStateSet(['display_name' => 'Jane Smith'])
        ->fillForm(['email' => 'Jane@Example.com', 'phone' => '(252) 555-0142'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Client::query()->sole())
        ->display_name->toBe('Jane Smith')
        ->email->toBe('jane@example.com')
        ->phone->toBe('+12525550142');
});

it('leaves a display name someone typed alone', function () {
    Livewire::test(CreateClient::class)
        ->fillForm(['display_name' => 'Smith family'])
        ->fillForm(['first_name' => 'Jane', 'last_name' => 'Smith'])
        ->assertSchemaStateSet(['display_name' => 'Smith family']);
});

it('needs an email or a phone', function () {
    Livewire::test(CreateClient::class)
        ->fillForm(['display_name' => 'Jane Smith'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required_without', 'phone' => 'required_without']);
});

it('won’t give two clients the same name, in any case, trashed ones included, since QuickBooks won’t', function () {
    Client::factory()->create(['display_name' => 'Jane Smith'])->delete();

    Livewire::test(CreateClient::class)
        ->fillForm(['display_name' => 'JANE SMITH', 'email' => 'jane@example.com'])
        ->call('create')
        ->assertHasFormErrors(['display_name']);
});

it('keeps a client’s own name when they’re edited', function () {
    $client = Client::factory()->create(['display_name' => 'Jane Smith']);

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->fillForm(['notes' => 'Prefers mornings'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->refresh()->notes)->toBe('Prefers mornings');
});

it('shows a client with their properties and projects', function () {
    $property = Property::factory()->create(['street' => '12 Front St']);
    $project = Project::factory()->for($property)->create(['title' => 'New double door']);

    $this->get(ClientResource::getUrl('view', ['record' => $property->client]))->assertOk();

    Livewire::test(PropertiesRelationManager::class, ['ownerRecord' => $property->client, 'pageClass' => ViewClient::class])
        ->assertCanSeeTableRecords([$property]);
    Livewire::test(ProjectsRelationManager::class, ['ownerRecord' => $property->client, 'pageClass' => ViewClient::class])
        ->assertCanSeeTableRecords([$project]);
});

it('trashes a client’s properties and projects with them, and restores only those', function () {
    $client = Client::factory()->create();
    $property = Property::factory()->for($client)->create();
    $project = Project::factory()->for($property)->create();
    $trashedEarlier = Project::factory()->for($property)->create();
    $trashedEarlier->delete();
    $this->travel(1)->minute();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])->callAction(DeleteAction::class);

    expect($property->refresh()->trashed())->toBeTrue()
        ->and($project->refresh()->trashed())->toBeTrue();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])->callAction(RestoreAction::class);

    expect($client->refresh()->trashed())->toBeFalse()
        ->and($property->refresh()->trashed())->toBeFalse()
        ->and($project->refresh()->trashed())->toBeFalse()
        ->and($trashedEarlier->refresh()->trashed())->toBeTrue();
});

it('erases a client with their properties, projects, and project photos', function () {
    Storage::fake('private');
    $client = Client::factory()->create();
    $project = Project::factory()->for(Property::factory()->for($client))->create();
    $project->addMedia(UploadedFile::fake()->image('door.jpg'))->toMediaCollection('photos');
    $client->delete();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])->callAction(ForceDeleteAction::class);

    expect(Client::withTrashed()->count())->toBe(0)
        ->and(Property::withTrashed()->count())->toBe(0)
        ->and(Project::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('finds a client by phone in global search', function () {
    Client::factory()->create(['display_name' => 'Jane Smith', 'phone' => '+12525550142']);

    expect(ClientResource::getGlobalSearchResults('5550142')->pluck('title')->all())->toBe(['Jane Smith']);
});

it('keeps clients out', function () {
    $this->actingAs(User::factory()->withMfa()->create());

    expect(auth()->user()->can('viewAny', Client::class))->toBeFalse();
});
