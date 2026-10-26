<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('question')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('answer')
                            ->required()
                            ->rows(4),
                        Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->preload()
                            ->helperText('Shows on that service’s page. Leave empty for the general FAQ list.'),
                        Toggle::make('is_published')
                            ->label('Show on the website')
                            ->default(true),
                    ]),
            ]);
    }
}
