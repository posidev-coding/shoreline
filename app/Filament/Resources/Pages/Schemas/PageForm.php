<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('intro')
                            ->helperText('One sentence under the title.')
                            ->maxLength(255),
                        ContentFields::richText('body')
                            ->label('Content')
                            ->required(),
                    ]),
                ContentFields::seoSection(),
            ]);
    }
}
