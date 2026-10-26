<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Support\ContactFields;
use App\Models\Client;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['properties', 'openProjects']))
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Client')
                    ->searchable(['display_name', 'first_name', 'last_name', 'company', 'email', 'phone'])
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Client $record): ?string => $record->company ?? $record->email),
                TextColumn::make('phone')
                    ->formatStateUsing(fn (string $state): ?string => ContactFields::national($state))
                    ->placeholder('None'),
                TextColumn::make('properties_count')
                    ->label('Properties')
                    ->numeric(),
                TextColumn::make('open_projects_count')
                    ->label('Open projects')
                    ->numeric(),
                IconColumn::make('is_property_manager')
                    ->label('Manager')
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::OutlinedBuildingOffice2 : null)
                    ->tooltip(fn (Client $record): ?string => $record->is_property_manager ? 'Property manager' : null),
            ])
            ->filters([
                TernaryFilter::make('is_property_manager')
                    ->label('Property managers'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No clients yet')
            ->emptyStateDescription('Convert a quote request, or add a client here.');
    }
}
