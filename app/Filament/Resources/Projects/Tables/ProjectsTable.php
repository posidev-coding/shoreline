<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStage;
use App\Models\Project;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Livewire\Component;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'property', 'service']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Project')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (Project $record, Component $livewire): string => collect([
                        $livewire instanceof RelationManager ? null : $record->client->display_name,
                        "{$record->property->street}, {$record->property->city}",
                    ])->filter()->implode(' · ')),
                TextColumn::make('stage')
                    ->badge()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->label('Service')
                    ->placeholder('None')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->options(ProjectStage::class)
                    ->multiple(),
                SelectFilter::make('service')
                    ->relationship('service', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No projects here')
            ->emptyStateDescription('Convert a quote request, or start a project from a client’s page.');
    }
}
