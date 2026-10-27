<?php

use App\Enums\OrderStatus;
use App\Enums\ProjectStage;
use App\Filament\Pages\PartsToOrder;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\LineItemsRelationManager;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('lists what’s still to order or on the way, grouped by supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Coastal Door Supply']);
    $toOrder = ProjectLineItem::factory()->create(['order_status' => OrderStatus::ToOrder, 'supplier_id' => $supplier->getKey()]);
    $onTheWay = ProjectLineItem::factory()->create(['order_status' => OrderStatus::Ordered]);
    $received = ProjectLineItem::factory()->create(['order_status' => OrderStatus::Received]);
    $fromStock = ProjectLineItem::factory()->create();

    Livewire::test(PartsToOrder::class)
        ->assertCanSeeTableRecords([$toOrder, $onTheWay])
        ->assertCanNotSeeTableRecords([$received, $fromStock])
        ->assertSee('Coastal Door Supply')
        ->assertSee('No supplier picked');

    expect(PartsToOrder::getNavigationBadge())->toBe('1');
});

it('marks a supplier’s parts ordered together, moving approved jobs along', function () {
    $approved = Project::factory()->stage(ProjectStage::Approved)->create();
    $lines = ProjectLineItem::factory()->count(2)->for($approved)->create(['order_status' => OrderStatus::ToOrder]);

    Livewire::test(PartsToOrder::class)
        ->selectTableRecords($lines->modelKeys())
        ->callAction(TestAction::make('markOrdered')->table()->bulk(), data: ['expected_on' => '2026-11-10'])
        ->assertHasNoErrors();

    expect($lines->map->refresh()->pluck('order_status')->unique()->all())->toBe([OrderStatus::Ordered])
        ->and($lines->first()->expected_on->toDateString())->toBe('2026-11-10')
        ->and($approved->refresh()->stage)->toBe(ProjectStage::MaterialsOrdered);
});

it('marks parts received, putting stocked ones into stock', function () {
    $spring = Part::factory()->spring()->create();
    $line = ProjectLineItem::factory()->create(['part_id' => $spring->getKey(), 'quantity' => 2, 'order_status' => OrderStatus::Ordered]);

    Livewire::test(PartsToOrder::class)
        ->selectTableRecords([$line->getKey()])
        ->callAction(TestAction::make('markReceived')->table()->bulk())
        ->assertHasNoErrors();

    expect($line->refresh()->order_status)->toBe(OrderStatus::Received)
        ->and($spring->refresh()->on_hand)->toBe('2.00');
});

it('fills a line item from the part picked', function () {
    $supplier = Supplier::factory()->create();
    $part = Part::factory()->opener()->create(['supplier_id' => $supplier->getKey()]);
    $project = Project::factory()->create();

    Livewire::test(LineItemsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
        ->mountAction(TestAction::make('create')->table())
        ->fillForm(['part_id' => $part->getKey()])
        ->assertActionDataSet(['description' => 'LiftMaster 87504 wall-mount opener', 'unit_price' => '849.0000', 'supplier_id' => $supplier->getKey()])
        ->fillForm(['order_status' => OrderStatus::ToOrder->value])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($project->lineItems()->sole())
        ->part_id->toBe($part->getKey())
        ->order_status->toBe(OrderStatus::ToOrder);
});
