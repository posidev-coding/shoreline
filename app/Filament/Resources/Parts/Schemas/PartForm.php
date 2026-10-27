<?php

namespace App\Filament\Resources\Parts\Schemas;

use App\Enums\PartCategory;
use App\Enums\PartUnit;
use App\Support\PartSpecs;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('category')
                            ->options(PartCategory::class)
                            ->required()
                            ->live(),
                        TextInput::make('name')
                            ->helperText('How it reads on a line item.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('manufacturer')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('Part number or SKU')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Still sold')
                            ->helperText('Turn off to hide it from new line items without losing its history.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('Details')
                    ->description('What to check when matching a replacement.')
                    ->columns(3)
                    ->schema(fn (Get $get): array => self::specFields(self::category($get))),
                Section::make('Buying and selling')
                    ->columns(3)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(255),
                            ]),
                        Select::make('unit')
                            ->options(PartUnit::class)
                            ->default(PartUnit::Each)
                            ->required(),
                        TextInput::make('warranty_months')
                            ->label('Warranty')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('months'),
                        TextInput::make('cost')
                            ->label('Our cost')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->step(0.0001),
                        TextInput::make('price')
                            ->label('Price to the customer')
                            ->helperText('Filled in as the rate when it’s added to a job.')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->step(0.0001),
                    ]),
                Section::make('Stock')
                    ->columns(3)
                    ->schema([
                        Toggle::make('track_stock')
                            ->label('Keep count of stock')
                            ->helperText('For parts kept on the truck or in the shop. Jobs take them out of stock when they’re completed.')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('starting_count')
                            ->label('On hand now')
                            ->helperText('The count to start from. Later changes go through Receive and Count on the part’s page.')
                            ->numeric()
                            ->minValue(0)
                            ->dehydrated(false)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && (bool) $get('track_stock')),
                        TextInput::make('reorder_point')
                            ->label('Reorder at')
                            ->helperText('It shows as low stock at or below this count.')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => (bool) $get('track_stock')),
                    ]),
                Section::make('Files')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photo')
                            ->collection('photo')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(intdiv((int) config('media-library.max_file_size'), 1024)),
                        SpatieMediaLibraryFileUpload::make('spec_sheet')
                            ->label('Spec sheet')
                            ->collection('spec_sheet')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(intdiv((int) config('media-library.max_file_size'), 1024)),
                    ]),
            ]);
    }

    /**
     * The category's own detail fields, then free key and value pairs for anything else.
     *
     * @return list<Select|TextInput|KeyValue>
     */
    private static function specFields(?PartCategory $category): array
    {
        $fields = [];

        foreach (PartSpecs::for($category) as $key => $spec) {
            $fields[] = isset($spec['options'])
                ? Select::make("specs.{$key}")->label($spec['label'])->options($spec['options'])
                : TextInput::make("specs.{$key}")->label($spec['label'])->maxLength(100)->suffix($spec['suffix'] ?? null)->placeholder($spec['placeholder'] ?? null);
        }

        $fields[] = KeyValue::make('specs.other')
            ->label($fields === [] ? 'Details' : 'Anything else')
            ->keyLabel('Detail')
            ->valueLabel('Value')
            ->columnSpanFull();

        return $fields;
    }

    private static function category(Get $get): ?PartCategory
    {
        $category = $get('category');

        return $category instanceof PartCategory ? $category : PartCategory::tryFrom((string) $category);
    }
}
