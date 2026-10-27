<?php

use App\Enums\County;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\PropertiesRelationManager;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Pages\PropertyHistory;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\Property;
use App\Models\ServiceArea;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('lists properties with their client and open projects', function () {
    $property = Property::factory()->create();
    Project::factory()->for($property)->create();

    Livewire::test(ListProperties::class)
        ->assertCanSeeTableRecords([$property])
        ->assertTableColumnStateSet('client.display_name', $property->client->display_name, $property)
        ->assertTableColumnStateSet('projects_count', 1, $property);
});

it('adds a property from a client’s page, filling the town and county from the service area', function () {
    $client = Client::factory()->create();
    $area = ServiceArea::factory()->create(['name' => 'Swansboro', 'county' => County::Onslow]);

    Livewire::test(PropertiesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
        ->mountAction(TestAction::make('create')->table())
        ->fillForm(['street' => '5 Water St', 'service_area_id' => $area->getKey()])
        ->assertActionDataSet(['city' => 'Swansboro', 'county' => County::Onslow])
        ->fillForm(['zip' => '28584'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($client->properties()->sole())
        ->street->toBe('5 Water St')
        ->city->toBe('Swansboro')
        ->county->toBe(County::Onslow);
});

it('keeps the access info encrypted, and out of the activity log', function () {
    $property = Property::factory()->create();

    Livewire::test(EditProperty::class, ['record' => $property->getKey()])
        ->fillForm(['access_info' => 'Lockbox 4821', 'label' => 'Beach house'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($property->refresh()->access_info)->toBe('Lockbox 4821')
        ->and(DB::table('properties')->value('access_info'))->not->toContain('4821')
        ->and(Activity::query()->pluck('attribute_changes')->toJson())->not->toContain('4821')
        ->and(Activity::query()->where('description', 'Changed the access info')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'updated')->sole()->attribute_changes['attributes'])->toBe(['label' => 'Beach house']);
});

it('shows a property, its access info, and its history', function () {
    $property = Property::factory()->vacationRental()->create();
    $property->update(['label' => 'Beach house']);

    $this->get(PropertyResource::getUrl('view', ['record' => $property]))
        ->assertOk()
        ->assertSee('Lockbox 4821')
        ->assertSee('Saturday changeover');

    Livewire::test(PropertyHistory::class, ['record' => $property->getKey()])
        ->assertOk()
        ->assertSee('Beach house');
});
