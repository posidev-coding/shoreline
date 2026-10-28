<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\OrderStatus;
use App\Models\CatalogItem;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Support\Usd;
use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The working draft of what the job costs, before tax. It becomes the QuickBooks estimate in M6.
 *
 * @property Project $ownerRecord
 */
class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';

    protected static ?string $title = 'Line items';

    protected static ?string $modelLabel = 'line item';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('part_id')
                    ->label('Part')
                    ->helperText('Optional. Picking one fills in the description, rate, and supplier.')
                    ->relationship('part', 'name', fn ($query) => $query->active()->orderBy('name'))
                    ->searchable(['name', 'manufacturer', 'model', 'sku'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => self::fillFromPart($set, $state))
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('Rate')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.0001)
                    ->required(),
                Toggle::make('is_taxable')
                    ->label('Taxable')
                    ->helperText('Set from the QuickBooks item. QuickBooks works out the tax.')
                    ->default(true)
                    ->inline(false),
                Select::make('catalog_item_id')
                    ->label('Bills in QuickBooks as')
                    ->options(fn (): array => CatalogItem::billableOptions())
                    ->searchable()
                    ->helperText('Every line needs one before an estimate goes to QuickBooks. A part fills in its own.')
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => self::fillFromCatalogItem($set, $state))
                    ->columnSpanFull(),
                Section::make('Ordering')
                    ->description('For parts bought for this job. Stocked parts come off the shelf when the job is completed.')
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(fn (?ProjectLineItem $record): bool => $record?->order_status === null)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('order_status')
                            ->label('Order')
                            ->options(OrderStatus::class)
                            ->placeholder('Nothing to order')
                            ->live(),
                        Select::make('supplier_id')
                            ->label('From')
                            ->relationship('supplier', 'name')
                            ->preload()
                            ->visible(fn (Get $get): bool => filled($get('order_status'))),
                        DatePicker::make('expected_on')
                            ->label('Expected')
                            ->native(false)
                            ->visible(fn (Get $get): bool => filled($get('order_status'))),
                    ]),
            ]);
    }

    /**
     * The part's name, price, and supplier, as a starting point the owner can change.
     */
    private static function fillFromPart(Set $set, ?string $partId): void
    {
        $part = filled($partId) ? Part::query()->find($partId) : null;

        if ($part === null) {
            return;
        }

        $set('description', $part->name);
        $set('unit_price', $part->price);
        $set('supplier_id', $part->supplier_id);

        if ($part->catalog_item_id !== null) {
            $set('catalog_item_id', $part->catalog_item_id);
            self::fillFromCatalogItem($set, (string) $part->catalog_item_id);
        }
    }

    /**
     * Whether the line is taxable starts as the QuickBooks item's setting; the owner can change it.
     */
    private static function fillFromCatalogItem(Set $set, ?string $catalogItemId): void
    {
        $item = filled($catalogItemId) ? CatalogItem::query()->find($catalogItemId) : null;

        if ($item !== null) {
            $set('is_taxable', $item->is_taxable);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn ($query) => $query->with('catalogItem'))
            ->reorderable('sort_order')
            ->paginated(false)
            ->description(fn (): string => 'Subtotal '.Usd::format($this->ownerRecord->subtotal()).' before tax. QuickBooks adds the tax when the estimate goes out.')
            ->columns([
                TextColumn::make('description')
                    ->wrap()
                    ->description(fn (ProjectLineItem $record): string => $record->catalogItem->fully_qualified_name ?? 'No QuickBooks item yet'),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn (string $state): string => (string) BigDecimal::of($state)->strippedOfTrailingZeros()),
                TextColumn::make('unit_price')
                    ->label('Rate')
                    ->formatStateUsing(fn (string $state): string => Usd::rate($state)),
                TextColumn::make('amount')
                    ->state(fn (ProjectLineItem $record): string => Usd::format($record->amount()))
                    ->weight('semibold'),
                TextColumn::make('is_taxable')
                    ->label('Tax')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'TAX' : 'NON')
                    ->color(fn (bool $state): string => $state ? 'gray' : 'warning'),
                TextColumn::make('order_status')
                    ->label('Order')
                    ->badge()
                    ->description(fn (ProjectLineItem $record): ?string => match ($record->order_status) {
                        OrderStatus::Ordered => $record->expected_on ? 'Due '.$record->expected_on->format('M j') : null,
                        OrderStatus::Received => $record->received_at?->timezone(config('app.business_timezone'))->format('M j'),
                        default => null,
                    })
                    ->placeholder(''),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add line')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'sort_order' => (int) $this->ownerRecord->lineItems()->max('sort_order') + 1,
                    ]),
            ])
            ->recordActions([
                Action::make('markOrdered')
                    ->label('Mark ordered')
                    ->icon(Heroicon::OutlinedTruck)
                    ->visible(fn (ProjectLineItem $record): bool => $record->order_status === OrderStatus::ToOrder)
                    ->schema([DatePicker::make('expected_on')->label('Expected')->native(false)])
                    ->modalWidth('sm')
                    ->action(fn (ProjectLineItem $record, array $data) => $record->update(['order_status' => OrderStatus::Ordered, 'expected_on' => $data['expected_on'] ?? null])),
                Action::make('markReceived')
                    ->label('Mark received')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProjectLineItem $record): bool => $record->order_status === OrderStatus::Ordered)
                    ->action(fn (ProjectLineItem $record) => $record->update(['order_status' => OrderStatus::Received])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No line items yet')
            ->emptyStateDescription('Add the parts and labor as you work out the price.');
    }
}
