<?php

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('opens on new requests, with a count in the navigation', function () {
    $new = Lead::factory()->count(2)->create();
    $contacted = Lead::factory()->contacted()->create();

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords($new)
        ->assertCanNotSeeTableRecords([$contacted]);

    expect(LeadResource::getNavigationBadge())->toBe('2');
});

it('shows a request with its details, contact links, and the person’s earlier requests', function () {
    $earlier = Lead::factory()->contacted()->create(['email' => 'jane@example.com', 'created_at' => now()->subMonth()]);
    $lead = Lead::factory()->create(['email' => 'Jane@Example.com', 'phone' => '+12525550142', 'answers' => ['door_size' => 'single', 'symptoms' => ['spring']]]);

    $this->get(LeadResource::getUrl('view', ['record' => $lead]))
        ->assertOk()
        ->assertSee('(252) 555-0142')
        ->assertSee('tel:+12525550142', false)
        ->assertSee('Door: One single door (8 to 10 ft wide)')
        ->assertSee('Broken spring, or I heard a loud bang')
        ->assertSee('Earlier requests')
        ->assertSee(LeadResource::getUrl('view', ['record' => $earlier]), false);
});

it('shows photos through short-lived links, and a HEIC that wouldn’t convert as a download', function () {
    Storage::fake('private');
    $lead = Lead::factory()->create();
    $lead->addMedia(base_path('tests/Fixtures/door-with-gps.jpg'))->preservingOriginal()->toMediaCollection('photos');
    $lead->addMedia(base_path('tests/Fixtures/door-corrupt.heic'))->preservingOriginal()->toMediaCollection('photos');

    $this->get(LeadResource::getUrl('view', ['record' => $lead]))
        ->assertOk()
        ->assertSee('door-corrupt.heic')
        ->assertSee('Open or download');
});

it('moves a request through contacted, spam, and back to new', function () {
    $lead = Lead::factory()->create();

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->callAction('markContacted');
    expect($lead->refresh())->status->toBe(LeadStatus::Contacted)->contacted_at->not->toBeNull();

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->callAction('markSpam');
    expect($lead->refresh()->status)->toBe(LeadStatus::Spam);

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->callAction('reopen');
    expect($lead->refresh()->status)->toBe(LeadStatus::New);
});

it('deletes a request and its photos when someone asks to be erased', function () {
    Storage::fake('private');
    $lead = Lead::factory()->create();
    $lead->addMedia(base_path('tests/Fixtures/door-with-gps.jpg'))->preservingOriginal()->toMediaCollection('photos');

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->callAction(DeleteAction::class);

    expect(Lead::query()->count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('never creates or edits a request by hand', function () {
    expect(auth()->user()->can('create', Lead::class))->toBeFalse()
        ->and(LeadResource::hasPage('create'))->toBeFalse()
        ->and(LeadResource::hasPage('edit'))->toBeFalse();
});

it('keeps clients out', function () {
    $this->actingAs(User::factory()->withMfa()->create());

    expect(auth()->user()->can('viewAny', Lead::class))->toBeFalse();
});
