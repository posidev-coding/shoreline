<?php

namespace App\Filament\Resources\Parts\Tables;

use App\Enums\PartCategory;
use App\Models\Part;
use App\Support\Usd;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Part')
                    ->searchable(['name', 'manufacturer', 'model', 'sku'])
                    ->sortable()
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Part $record): ?string => collect([trim($record->manufacturer.' '.$record->model), $record->sku])->filter()->implode(' · ') ?: null),
                TextColumn::make('category')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('None')
                    ->toggleable(),
                TextColumn::make('price')
                    ->formatStateUsing(fn (string $state): string => Usd::rate($state))
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->state(fn (Part $record): ?string => $record->track_stock ? $record->quantityLabel($record->on_hand) : null)
                    ->placeholder('Not counted')
                    ->color(fn (Part $record): ?string => $record->isLowStock() ? 'danger' : null)
                    ->weight(fn (Part $record): ?string => $record->isLowStock() ? 'bold' : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(PartCategory::class)
                    ->multiple(),
                SelectFilter::make('manufacturer')
                    ->options(fn (): array => Part::query()->whereNotNull('manufacturer')->distinct()->orderBy('manufacturer')->pluck('manufacturer', 'manufacturer')->all()),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name'),
                Filter::make('low_stock')
                    ->label('Low stock')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('track_stock', true)->whereNotNull('reorder_point')->whereColumn('on_hand', '<=', 'reorder_point')),
                TernaryFilter::make('is_active')
                    ->label('Still sold')
                    ->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No parts here')
            ->emptyStateDescription('Add the doors, openers, springs, and hardware you install, so jobs can pick them.');
    }
}
