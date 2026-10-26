<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Enums\County;
use App\Enums\PropertyType;
use App\Filament\Support\ContactFields;
use App\Models\ServiceArea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            // On a client's page, the property is theirs.
                            ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager)
                            ->columnSpan(2),
                        TextInput::make('label')
                            ->helperText('Optional, like “Beach house”.')
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('street')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('service_area_id')
                            ->label('Service area')
                            ->relationship('serviceArea', 'name', fn ($query) => $query->ordered())
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => self::fillFromServiceArea($get, $set, $state))
                            ->helperText('Fills in the town and county.')
                            ->columnSpan(2),
                        TextInput::make('city')
                            ->label('Town')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('zip')
                            ->label('ZIP')
                            ->required()
                            ->maxLength(10),
                        TextInput::make('state')
                            ->default('NC')
                            ->required()
                            ->length(2),
                        Select::make('county')
                            ->options(County::class)
                            ->helperText('QuickBooks uses the address to work out sales tax.'),
                        Select::make('type')
                            ->options(PropertyType::class)
                            ->default(PropertyType::Primary)
                            ->required()
                            ->columnSpan(2),
                    ]),
                Section::make('On site')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_contact_name')
                            ->label('Contact on site')
                            ->helperText('A tenant, or the rental company’s manager.')
                            ->maxLength(255),
                        ContactFields::phone('site_contact_phone', 'Their phone'),
                        TextInput::make('turnover_notes')
                            ->label('When work can happen')
                            ->placeholder('Saturday changeover, 10 AM to 4 PM')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('access_info')
                            ->label('Access info')
                            ->helperText('Lockbox and gate codes. Stored encrypted, and kept out of the activity log.')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Picking a service area fills in its town and county, without overwriting a town typed by hand.
     */
    private static function fillFromServiceArea(Get $get, Set $set, ?string $serviceAreaId): void
    {
        $area = filled($serviceAreaId) ? ServiceArea::query()->find($serviceAreaId) : null;

        if ($area === null) {
            return;
        }

        if (blank($get('city'))) {
            $set('city', $area->name);
        }

        $set('county', $area->county);
    }
}
