<?php

use App\Enums\County;
use App\Enums\LeadStatus;
use App\Enums\ProjectStage;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\LeadConverter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
    $this->lead = Lead::factory()->create([
        'name' => 'Jane Q. Smith',
        'email' => 'jane@example.com',
        'phone' => '+12525550142',
        'street' => '12 Front St.',
        'town' => 'Beaufort',
        'zip' => '28516',
        'service_id' => Service::factory()->create(['name' => 'Garage Door Repair'])->getKey(),
        'service_area_id' => ServiceArea::factory()->create(['name' => 'Beaufort', 'county' => County::Carteret])->getKey(),
        'answers' => ['door_size' => 'double'],
        'message' => 'Heard a bang this morning.',
    ]);
});

it('makes a new client, property, and project from a request by someone new', function () {
    Livewire::test(ViewLead::class, ['record' => $this->lead->getKey()])
        ->mountAction('convert')
        ->assertActionDataSet(['client' => 'new', 'display_name' => 'Jane Q. Smith', 'title' => 'Garage Door Repair at 12 Front St.'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertRedirect(ProjectResource::getUrl('view', ['record' => Project::query()->sole()]));

    $project = Project::query()->sole();

    expect($project)
        ->title->toBe('Garage Door Repair at 12 Front St.')
        ->stage->toBe(ProjectStage::Estimating)
        ->service_id->toBe($this->lead->service_id)
        ->lead_id->toBe($this->lead->getKey())
        ->description->toBe("Door: One double door (16 to 18 ft wide)\nHeard a bang this morning.")
        ->and($project->client)
        ->display_name->toBe('Jane Q. Smith')
        ->first_name->toBe('Jane')
        ->last_name->toBe('Q. Smith')
        ->email->toBe('jane@example.com')
        ->phone->toBe('+12525550142')
        ->billing_street->toBe('12 Front St.')
        ->and($project->property)
        ->street->toBe('12 Front St.')
        ->city->toBe('Beaufort')
        ->county->toBe(County::Carteret)
        ->service_area_id->toBe($this->lead->service_area_id)
        ->and($this->lead->refresh()->status)->toBe(LeadStatus::Converted);
});

it('suggests the client we already have with that email, and their property at that address', function () {
    $client = Client::factory()->create(['email' => 'JANE@example.com', 'phone' => '+19105550000']);
    Property::factory()->for($client)->create(['street' => '40 Ann St', 'zip' => '28516']);
    $home = Property::factory()->for($client)->create(['street' => '12 front st', 'zip' => '28516']);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getKey()])
        ->mountAction('convert')
        ->assertActionDataSet(['client' => (string) $client->getKey(), 'property' => (string) $home->getKey()])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Client::query()->count())->toBe(1)
        ->and(Property::query()->count())->toBe(2)
        ->and(Project::query()->sole()->property_id)->toBe($home->getKey());
});

it('matches a client by phone too, and adds a new property for a new address', function () {
    $client = Client::factory()->create(['email' => 'someone.else@example.com', 'phone' => '+12525550142']);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getKey()])
        ->mountAction('convert')
        ->assertActionDataSet(['client' => (string) $client->getKey(), 'property' => 'new'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Client::query()->count())->toBe(1)
        ->and($client->properties()->sole()->street)->toBe('12 Front St.');
});

it('lets the owner make a new client even when one matches', function () {
    Client::factory()->create(['email' => 'jane@example.com']);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getKey()])
        ->callAction('convert', data: ['client' => 'new'])
        ->assertHasNoFormErrors();

    expect(Client::query()->count())->toBe(2);
});

it('tells two clients with the same name apart by town', function () {
    Client::factory()->create(['display_name' => 'Jane Q. Smith', 'email' => 'other@example.com', 'phone' => null]);

    expect(LeadConverter::availableDisplayName($this->lead))->toBe('Jane Q. Smith (Beaufort)');

    Client::factory()->create(['display_name' => 'Jane Q. Smith (Beaufort)', 'email' => 'third@example.com', 'phone' => null]);

    expect(LeadConverter::availableDisplayName($this->lead))->toBe('Jane Q. Smith (Beaufort) 2');
});

it('converts a request only once', function () {
    $converter = app(LeadConverter::class);
    $converter->convert($this->lead, null, null, 'Spring repair');

    expect(fn () => $converter->convert($this->lead, null, null, 'Spring repair again'))->toThrow(RuntimeException::class)
        ->and(Project::query()->count())->toBe(1);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getKey()])
        ->assertActionHidden('convert')
        ->assertActionHidden('markSpam')
        ->assertActionVisible('openProject');
});

it('won’t put a project at another client’s property', function () {
    $client = Client::factory()->create();
    $someoneElsesProperty = Property::factory()->create();

    expect(fn () => app(LeadConverter::class)->convert($this->lead, $client, $someoneElsesProperty, 'Spring repair'))
        ->toThrow(RuntimeException::class);
});

it('shows the request’s photos on the project', function () {
    $project = app(LeadConverter::class)->convert($this->lead, null, null, 'Spring repair');

    $this->get(ProjectResource::getUrl('view', ['record' => $project]))
        ->assertOk()
        ->assertSee('From the quote request');
});
