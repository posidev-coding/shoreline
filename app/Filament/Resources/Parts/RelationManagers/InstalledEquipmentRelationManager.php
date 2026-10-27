<?php

namespace App\Filament\Resources\Parts\RelationManagers;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\InstalledEquipment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Where this part is installed: the properties to call on a recall or a warranty question.
 */
class InstalledEquipmentRelationManager extends RelationManager
{
    protected static string $relationship = 'installedEquipment';

    protected static ?string $title = 'Where installed';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('property.client'))
            ->defaultSort('installed_on', 'desc')
            ->columns([
                TextColumn::make('property.street')
                    ->label('Property')
                    ->description(fn (InstalledEquipment $record): string => $record->property->client->display_name)
                    ->url(fn (InstalledEquipment $record): string => PropertyResource::getUrl('view', ['record' => $record->property_id])),
                TextColumn::make('serial')
                    ->placeholder('None'),
                TextColumn::make('installed_on')
                    ->label('Installed')
                    ->date()
                    ->placeholder('Unknown'),
                TextColumn::make('warranty_expires_on')
                    ->label('Warranty until')
                    ->date()
                    ->placeholder('None'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->emptyStateHeading('Not recorded at any property yet');
    }
}
