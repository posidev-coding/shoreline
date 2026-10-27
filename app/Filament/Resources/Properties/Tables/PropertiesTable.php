<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Enums\PropertyType;
use App\Models\Property;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Livewire\Component;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('client')->withCount(['projects' => fn ($query) => $query->open()]))
            ->defaultSort('street')
            ->columns([
                TextColumn::make('street')
                    ->label('Address')
                    ->searchable(['street', 'city', 'label'])
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Property $record): string => collect([$record->city, $record->label])->filter()->implode(' · ')),
                TextColumn::make('client.display_name')
                    ->label('Client')
                    ->searchable()
                    ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('type'),
                TextColumn::make('projects_count')
                    ->label('Open projects')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(PropertyType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No properties yet')
            ->emptyStateDescription('Properties are added from a client’s page, or when a quote request is converted.');
    }
}
