<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\ServiceIcon;
use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        ContentFields::name(),
                        ContentFields::slug(),
                        Select::make('icon')
                            ->options(ServiceIcon::class)
                            ->required(),
                        Toggle::make('is_published')
                            ->label('Show on the website')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('headline')
                            ->helperText('The big heading on the service page. Leave empty to use the name.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('summary')
                            ->required()
                            ->maxLength(155)
                            ->rows(2)
                            ->helperText('One or two sentences for the service cards. Up to 155 characters.')
                            ->columnSpanFull(),
                        ContentFields::richText('body')
                            ->label('Page content')
                            ->required(),
                    ]),
                ContentFields::seoSection(),
            ]);
    }
}
