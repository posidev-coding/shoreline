<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStage;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\Project;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Job')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('stage')
                                    ->badge()
                                    ->helperText(fn (Project $record): ?string => $record->stage_changed_at ? 'Since '.$record->stage_changed_at->timezone(config('app.business_timezone'))->toFormattedDayDateString() : null),
                                TextEntry::make('service.name')
                                    ->label('Service')
                                    ->placeholder('None'),
                                TextEntry::make('lost_reason')
                                    ->label('Why it was lost')
                                    ->visible(fn (Project $record): bool => $record->stage === ProjectStage::Lost)
                                    ->placeholder('Not recorded')
                                    ->columnSpanFull(),
                                TextEntry::make('description')
                                    ->label('Scope of work')
                                    ->placeholder('None yet')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Photos')
                            ->schema([
                                ViewEntry::make('photos')
                                    ->hiddenLabel()
                                    ->view('filament.projects.photos'),
                            ]),
                    ]),
                Section::make('Client')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('client.display_name')
                            ->hiddenLabel()
                            ->weight('semibold')
                            ->url(fn (Project $record): string => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('property')
                            ->label('Property')
                            ->state(fn (Project $record): string => $record->property->address())
                            ->url(fn (Project $record): string => PropertyResource::getUrl('view', ['record' => $record->property_id])),
                        TextEntry::make('property.access_info')
                            ->label('Access info')
                            ->visible(fn (Project $record): bool => filled($record->property->access_info))
                            ->copyable(),
                        TextEntry::make('property.turnover_notes')
                            ->label('When work can happen')
                            ->visible(fn (Project $record): bool => filled($record->property->turnover_notes)),
                        TextEntry::make('lead')
                            ->label('From the quote request')
                            ->state(fn (Project $record): ?string => $record->lead?->created_at?->timezone(config('app.business_timezone'))->toFormattedDateString())
                            ->url(fn (Project $record): ?string => $record->lead_id ? LeadResource::getUrl('view', ['record' => $record->lead_id]) : null)
                            ->visible(fn (Project $record): bool => $record->lead_id !== null),
                    ]),
            ]);
    }
}
