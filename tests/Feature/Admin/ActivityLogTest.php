<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('records who changed a client, and what', function () {
    $owner = User::factory()->owner()->withMfa()->create();
    $this->actingAs($owner);
    $client = Client::factory()->create(['notes' => null]);

    $client->update(['notes' => 'Gate code changes monthly']);

    expect(Activity::query()->where('event', 'updated')->sole())
        ->causer_id->toBe($owner->getKey())
        ->attribute_changes->toArray()->toEqual(['attributes' => ['notes' => 'Gate code changes monthly'], 'old' => ['notes' => null]]);
});

it('shows the whole log to super admins', function () {
    $this->actingAs(User::factory()->superAdmin()->withMfa()->create());
    Client::factory()->create();

    $this->get(ActivityResource::getUrl())->assertOk();
    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords(Activity::all())
        ->callAction(TestAction::make('view')->table(Activity::query()->first()))
        ->assertHasNoErrors();
});

it('keeps the whole log from the owner, who sees each record’s history instead', function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());

    $this->get(ActivityResource::getUrl())->assertForbidden();
});
