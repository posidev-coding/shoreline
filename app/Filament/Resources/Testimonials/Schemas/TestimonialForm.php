<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Enums\ReviewSource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->description('Only real reviews from real customers, in their own words. Fixing an obvious typo is fine; changing what they said is not.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('quote')
                            ->label('Review')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('author_name')
                            ->label('Name as shown')
                            ->required()
                            ->maxLength(255)
                            ->helperText('First name and last initial is plenty.'),
                        Select::make('service_area_id')
                            ->label('Town')
                            ->relationship('serviceArea', 'name')
                            ->preload(),
                        Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->preload(),
                        Select::make('rating')
                            ->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'])
                            ->helperText('As the customer gave it. Leave empty if there was no rating.'),
                        Select::make('source')
                            ->options(ReviewSource::class)
                            ->default(ReviewSource::Google)
                            ->required(),
                        TextInput::make('source_url')
                            ->label('Link to the review')
                            ->url()
                            ->maxLength(255),
                        DatePicker::make('reviewed_on')
                            ->label('Date of the review')
                            ->native(false),
                    ]),
                Section::make('Publishing')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Show on the website')
                            ->helperText('Only with the customer’s okay for reviews they sent you directly.'),
                        Toggle::make('is_featured')
                            ->label('Feature on the home page'),
                    ]),
            ]);
    }
}
