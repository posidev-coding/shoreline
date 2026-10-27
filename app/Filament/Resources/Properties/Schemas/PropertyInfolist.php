<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\ContactFields;
use App\Models\Property;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PropertyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Property')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')
                            ->state(fn (Property $record): string => $record->address())
                            ->url(fn (Property $record): string => $record->mapUrl(), shouldOpenInNewTab: true)
                            ->weight('semibold'),
                        TextEntry::make('client.display_name')
                            ->label('Client')
                            ->url(fn (Property $record): string => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('label')
                            ->placeholder('None'),
                        TextEntry::make('type'),
                        TextEntry::make('serviceArea.name')
                            ->label('Service area')
                            ->placeholder('Outside the service areas'),
                        TextEntry::make('county')
                            ->placeholder('Unknown'),
                        TextEntry::make('notes')
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),
                Section::make('On site')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('site_contact_name')
                            ->label('Contact on site')
                            ->placeholder('None'),
                        TextEntry::make('site_contact_phone')
                            ->label('Their phone')
                            ->formatStateUsing(fn (string $state): ?string => ContactFields::national($state))
                            ->url(fn (Property $record): ?string => filled($record->site_contact_phone) ? 'tel:'.$record->site_contact_phone : null)
                            ->placeholder('None'),
                        TextEntry::make('turnover_notes')
                            ->label('When work can happen')
                            ->placeholder('Any time'),
                        TextEntry::make('access_info')
                            ->label('Access info')
                            ->placeholder('None')
                            ->copyable(),
                    ]),
            ]);
    }
}
