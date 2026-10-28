<?php

namespace App\Filament\Resources\CatalogItems;

use App\Enums\CatalogItemType;
use App\Filament\Resources\CatalogItems\Pages\ManageCatalogItems;
use App\Models\CatalogItem;
use App\Support\Usd;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * QuickBooks' products and services, as last imported. Edited in QuickBooks, never here: parts
 * and job lines pick the one they bill as.
 */
class CatalogItemResource extends Resource
{
    protected static ?string $model = CatalogItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'QuickBooks items';

    protected static ?string $modelLabel = 'QuickBooks item';

    protected static ?string $pluralModelLabel = 'QuickBooks items';

    protected static ?string $slug = 'quickbooks-items';

    protected static ?string $recordTitleAttribute = 'fully_qualified_name';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fully_qualified_name')
            ->columns([
                TextColumn::make('fully_qualified_name')
                    ->label('Item')
                    ->searchable(['fully_qualified_name', 'sku', 'description'])
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (CatalogItem $record): ?string => $record->description)
                    ->wrap(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (CatalogItemType $state): string => $state->isBillable() ? 'primary' : 'gray'),
                TextColumn::make('unit_price')
                    ->label('Rate')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : Usd::rate($state))
                    ->placeholder('None'),
                IconColumn::make('is_taxable')
                    ->label('Taxable')
                    ->boolean(),
                TextColumn::make('income_account_name')
                    ->label('Income account')
                    ->placeholder('None')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),
                SelectFilter::make('type')
                    ->options(CatalogItemType::class),
            ])
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->emptyStateHeading('No QuickBooks items yet')
            ->emptyStateDescription('Import them from QuickBooks once it’s connected. They’re edited in QuickBooks, not here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCatalogItems::route('/'),
        ];
    }
}
