<?php

namespace App\Filament\Resources\Showcases\Schemas;

use App\Filament\Support\ContentFields;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ShowcaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        ContentFields::name('title', 'Title'),
                        ContentFields::slug(),
                        Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->preload(),
                        Select::make('service_area_id')
                            ->label('Town')
                            ->relationship('serviceArea', 'name')
                            ->preload()
                            ->helperText('Only the town is ever shown, never the street.'),
                        ContentFields::richText('story')
                            ->label('The story')
                            ->helperText('What was wrong, what you did, and what the customer got. No street names or house numbers.'),
                    ]),
                Section::make('Photos')
                    ->description('The uploaded originals stay private, location data included. Visitors only see resized copies with the photo metadata removed.')
                    ->columns(2)
                    ->schema([
                        self::photo('before', 'Before'),
                        self::photo('after', 'After'),
                    ]),
                Section::make('Publishing')
                    ->columns(3)
                    ->schema([
                        Toggle::make('has_photo_release')
                            ->label('Customer agreed to show these photos')
                            ->live()
                            ->inline(false),
                        Toggle::make('is_featured')
                            ->label('Feature on the home page')
                            ->inline(false),
                        DateTimePicker::make('published_at')
                            ->label('Publish on')
                            ->helperText('Leave empty to keep it as a draft.')
                            ->native(false)
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($value) && ! $get('has_photo_release')) {
                                    $fail('Get the customer’s photo release before publishing.');
                                }
                            }),
                    ]),
            ]);
    }

    private static function photo(string $collection, string $label): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($collection)
            ->label($label)
            ->collection($collection)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(20 * 1024)
            ->required();
    }
}
