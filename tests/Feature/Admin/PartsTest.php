<?php

use App\Enums\PartCategory;
use App\Enums\StockMovementType;
use App\Filament\Resources\Parts\Pages\CreatePart;
use App\Filament\Resources\Parts\Pages\ListParts;
use App\Filament\Resources\Parts\Pages\ViewPart;
use App\Filament\Resources\Parts\PartResource;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Part;
use App\Models\ProjectLineItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Stock;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());
});

it('adds a spring with its details and a starting count that has a history', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(CreatePart::class)
        ->fillForm(['category' => PartCategory::TorsionSpring->value])
        ->fillForm([
            'name' => 'Torsion spring 0.243 x 2 x 32, left wind',
            'specs' => ['wire_size' => '0.243', 'inside_diameter' => '2', 'length' => '32', 'wind' => 'left'],
            'supplier_id' => $supplier->getKey(),
            'price' => '89',
            'track_stock' => true,
        ])
        ->fillForm(['starting_count' => '4', 'reorder_point' => '2'])
        ->call('create')
        ->assertHasNoFormErrors();

    $part = Part::query()->sole();

    expect($part)
        ->specs->toMatchArray(['wire_size' => '0.243', 'wind' => 'left'])
        ->on_hand->toBe('4.00')
        ->and($part->movements()->sole())
        ->type->toBe(StockMovementType::Adjusted)
        ->note->toBe('Starting count');

    $this->get(PartResource::getUrl('view', ['record' => $part]))
        ->assertOk()
        ->assertSee('Wire size: 0.243 in')
        ->assertSee('Wind: Left');
});

it('receives, counts, and writes off stock from the part’s page', function () {
    $part = Part::factory()->spring()->create();

    Livewire::test(ViewPart::class, ['record' => $part->getKey()])
        ->callAction('receive', data: ['quantity' => 10, 'note' => 'Invoice 4471'])
        ->callAction(TestAction::make('count'), data: ['quantity' => 9])
        ->callAction(TestAction::make('writeOff'), data: ['quantity' => 1, 'note' => 'Rusted'])
        ->assertHasNoErrors();

    expect($part->refresh()->on_hand)->toBe('8.00')
        ->and($part->movements()->pluck('quantity')->all())->toBe(['10.00', '-1.00', '-1.00']);
});

it('offers stock actions only for parts it counts', function () {
    $opener = Part::factory()->opener()->create();

    Livewire::test(ViewPart::class, ['record' => $opener->getKey()])
        ->assertActionHidden('receive')
        ->assertSee('Not counted');
});

it('flags parts at or below their reorder point', function () {
    $low = Part::factory()->spring()->create();
    app(Stock::class)->receive($low, '2');
    $plenty = Part::factory()->spring()->create();
    app(Stock::class)->receive($plenty, '9');

    Livewire::test(ListParts::class)
        ->filterTable('low_stock', true)
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$plenty]);

    expect(PartResource::getNavigationBadge())->toBe('1');
});

it('deletes only a part nothing uses', function () {
    $unused = Part::factory()->create();
    $used = Part::factory()->create();
    ProjectLineItem::factory()->create(['part_id' => $used->getKey()]);

    Livewire::test(ViewPart::class, ['record' => $used->getKey()])->assertActionHidden(DeleteAction::class);
    Livewire::test(ViewPart::class, ['record' => $unused->getKey()])->callAction(DeleteAction::class);

    expect(Part::query()->pluck('id')->all())->toBe([$used->getKey()]);
});

it('keeps suppliers on one page', function () {
    Livewire::test(ManageSuppliers::class)
        ->callAction(TestAction::make('create'), data: ['name' => 'Coastal Door Supply', 'rep_phone' => '(910) 555-0100'])
        ->assertHasNoFormErrors();

    expect(Supplier::query()->sole())
        ->name->toBe('Coastal Door Supply')
        ->rep_phone->toBe('+19105550100');
});

it('keeps clients out', function () {
    $this->actingAs(User::factory()->withMfa()->create());

    expect(auth()->user()->can('viewAny', Part::class))->toBeFalse()
        ->and(auth()->user()->can('viewAny', Supplier::class))->toBeFalse();
});
