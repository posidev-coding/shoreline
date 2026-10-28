<?php

use App\Enums\CatalogItemType;
use App\Filament\Resources\CatalogItems\Pages\ManageCatalogItems;
use App\Filament\Resources\Parts\Pages\CreatePart;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\LineItemsRelationManager;
use App\Jobs\ImportQuickBooksItems;
use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\CatalogItem;
use App\Models\Part;
use App\Models\Project;
use App\Models\QuickBooksConnection;
use App\Models\User;
use App\Services\QuickBooks\ItemImporter;
use App\Services\QuickBooks\QuickBooksException;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
    $this->connection = QuickBooksConnection::factory()->create(['realm_id' => '9130350000000001']);
});

/**
 * Fakes the two item queries: active items, then inactive ones.
 *
 * @param  list<array<string, mixed>>  $active
 * @param  list<array<string, mixed>>  $inactive
 */
function fakeItems(array $active, array $inactive = []): void
{
    Http::fake(['sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/query*' => function (Request $request) use ($active, $inactive) {
        $rows = str_contains(urldecode($request->url()), 'Active = false') ? $inactive : $active;

        return Http::response(['QueryResponse' => $rows === [] ? [] : ['Item' => $rows]]);
    }]);
}

function qbItem(string $id, string $name, string $type = 'Service', array $extra = []): array
{
    return [
        'Id' => $id, 'Name' => $name, 'FullyQualifiedName' => $name, 'Type' => $type, 'Active' => true,
        'SyncToken' => '0', 'MetaData' => ['LastUpdatedTime' => '2026-10-20T09:15:00-07:00'], ...$extra,
    ];
}

describe('import', function () {
    it('brings in active and inactive items, keyed by QuickBooks id', function () {
        fakeItems([
            qbItem('1', 'Labor', extra: ['UnitPrice' => 95, 'Taxable' => false, 'IncomeAccountRef' => ['value' => '79', 'name' => 'Services']]),
            qbItem('2', 'Doors', 'Category'),
            qbItem('3', 'Doors:Spring', 'NonInventory', ['ParentRef' => ['value' => '2'], 'FullyQualifiedName' => 'Doors:Spring', 'Taxable' => true, 'Sku' => 'SPR-243']),
            qbItem('4', 'Gift card', 'SomethingNew'),
        ], [qbItem('5', 'Old service', extra: ['Active' => false])]);

        $counts = app(ItemImporter::class)->import($this->connection);

        expect($counts)->toBe(['created' => 5, 'updated' => 0])
            ->and(CatalogItem::query()->where('qb_id', '1')->sole())
            ->unit_price->toBe('95.0000')
            ->is_taxable->toBeFalse()
            ->income_account_name->toBe('Services')
            ->qb_last_updated_at->toIso8601String()->toBe('2026-10-20T16:15:00+00:00')
            ->and(CatalogItem::query()->where('qb_id', '3')->sole())
            ->fully_qualified_name->toBe('Doors:Spring')
            ->parent_qb_id->toBe('2')
            ->sku->toBe('SPR-243')
            ->and(CatalogItem::query()->where('qb_id', '4')->sole()->type)->toBe(CatalogItemType::Other)
            ->and(CatalogItem::query()->where('qb_id', '5')->sole()->is_active)->toBeFalse()
            ->and(CatalogItem::billableOptions())->toBe(
                CatalogItem::query()->whereIn('qb_id', ['3', '1'])->orderBy('fully_qualified_name')->pluck('fully_qualified_name', 'id')->all()
            );
    });

    it('updates on a second run instead of duplicating', function () {
        // Each run asks twice: active items, then inactive ones.
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => Http::sequence()
            ->push(['QueryResponse' => ['Item' => [qbItem('1', 'Labor', extra: ['UnitPrice' => 95])]]])
            ->push(['QueryResponse' => []])
            ->push(['QueryResponse' => ['Item' => [qbItem('1', 'Labor (hourly)', extra: ['UnitPrice' => 105, 'SyncToken' => '1'])]]])
            ->push(['QueryResponse' => []])]);

        app(ItemImporter::class)->import($this->connection);
        $counts = app(ItemImporter::class)->import($this->connection);

        expect($counts)->toBe(['created' => 0, 'updated' => 1])
            ->and(CatalogItem::query()->sole())->name->toBe('Labor (hourly)')->unit_price->toBe('105.0000');
    });

    it('runs as a job that says in the bell how it went', function () {
        fakeItems([qbItem('1', 'Labor')]);

        ImportQuickBooksItems::dispatchSync($this->owner);

        expect($this->owner->notifications()->sole()->data['title'])->toBe('QuickBooks items imported')
            ->and($this->owner->notifications()->sole()->data['body'])->toBe('1 new, 0 updated.');
    });

    it('puts the job back for a minute when QuickBooks is busy', function () {
        $job = (new ImportQuickBooksItems)->withFakeQueueInteractions();

        (new BackOffWhenQuickBooksIsBusy)->handle($job, fn () => throw new QuickBooksException('Too many', 429));

        $job->assertReleased(BackOffWhenQuickBooksIsBusy::RETRY_AFTER_SECONDS);
    });

    it('lets any other QuickBooks error fail the job', function () {
        $job = (new ImportQuickBooksItems)->withFakeQueueInteractions();

        expect(fn () => (new BackOffWhenQuickBooksIsBusy)->handle($job, fn () => throw new QuickBooksException('Broken', 500)))
            ->toThrow(QuickBooksException::class);
    });

    it('imports from the items page, only once QuickBooks is connected', function () {
        Queue::fake();

        Livewire::test(ManageCatalogItems::class)->callAction(TestAction::make('import'));
        Queue::assertPushed(ImportQuickBooksItems::class, fn (ImportQuickBooksItems $job): bool => $job->requestedBy?->is($this->owner) === true);

        $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);
        Livewire::test(ManageCatalogItems::class)->assertActionHidden(TestAction::make('import'));
    });
});

describe('billing as an item', function () {
    it('lets a part name the QuickBooks item it bills as', function () {
        $spring = CatalogItem::factory()->nonInventory()->create(['fully_qualified_name' => 'Doors:Spring']);
        $category = CatalogItem::factory()->create(['type' => CatalogItemType::Category, 'fully_qualified_name' => 'Doors']);

        Livewire::test(CreatePart::class)
            ->assertFormFieldExists('catalog_item_id', fn (Select $field): bool => array_key_exists($spring->getKey(), $field->getOptions())
                && ! array_key_exists($category->getKey(), $field->getOptions()))
            ->fillForm(['category' => 'torsion_spring', 'name' => 'Torsion spring 0.243', 'unit' => 'each', 'catalog_item_id' => $spring->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Part::query()->sole()->catalog_item_id)->toBe($spring->getKey());
    });

    it('fills a line’s item and tax setting from its part, or from the item picked', function () {
        $spring = CatalogItem::factory()->nonInventory()->create();
        $labor = CatalogItem::factory()->create(['is_taxable' => false]);
        $part = Part::factory()->create(['catalog_item_id' => $spring->getKey(), 'price' => '89.0000']);
        $project = Project::factory()->create();

        $tab = Livewire::test(LineItemsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class]);

        $tab->mountAction(TestAction::make('create')->table())
            ->fillForm(['part_id' => $part->getKey()])
            ->assertSchemaStateSet(['catalog_item_id' => $spring->getKey(), 'is_taxable' => true])
            ->fillForm(['catalog_item_id' => $labor->getKey()])
            ->assertSchemaStateSet(['is_taxable' => false]);
    });
});
