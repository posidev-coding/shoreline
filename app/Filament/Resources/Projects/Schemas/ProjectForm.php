<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStage;
use App\Models\Client;
use App\Models\Property;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        // Only narrows the properties: the project's client is always the property's.
                        Select::make('client_id')
                            ->label('Client')
                            ->options(fn (): array => Client::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn (Set $set) => $set('property_id', null))
                            ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager),
                        Select::make('property_id')
                            ->label('Property')
                            ->options(fn (Get $get, Component $livewire): array => self::propertyOptions($get, $livewire))
                            ->searchable()
                            ->required()
                            ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Property),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name', fn ($query) => $query->ordered())
                            ->preload(),
                        Select::make('stage')
                            ->options(ProjectStage::class)
                            ->default(ProjectStage::Estimating)
                            ->required()
                            ->live(),
                        Textarea::make('lost_reason')
                            ->label('Why it was lost')
                            ->rows(2)
                            ->visible(fn (Get $get): bool => self::stage($get) === ProjectStage::Lost)
                            ->required(fn (Get $get): bool => self::stage($get) === ProjectStage::Lost)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Scope of work')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Photos')
                    ->description('Private: only you see them. Take them on site from your phone.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->hiddenLabel()
                            ->collection('photos')
                            ->conversion('thumb')
                            ->multiple()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(intdiv((int) config('media-library.max_file_size'), 1024)),
                    ]),
            ]);
    }

    /**
     * The stage picked in the form, which is the enum on load and its value once changed.
     */
    public static function stage(Get $get): ?ProjectStage
    {
        $stage = $get('stage');

        return $stage instanceof ProjectStage ? $stage : ProjectStage::tryFrom((string) $stage);
    }

    /**
     * The chosen client's properties, or the client's whose page this is.
     *
     * @return array<int, string>
     */
    private static function propertyOptions(Get $get, Component $livewire): array
    {
        $clientId = $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Client
            ? $livewire->getOwnerRecord()->getKey()
            : $get('client_id');

        if (blank($clientId)) {
            return [];
        }

        return Property::query()
            ->where('client_id', $clientId)
            ->orderBy('street')
            ->get()
            ->mapWithKeys(fn (Property $property): array => [$property->getKey() => $property->address()])
            ->all();
    }
}
