<?php

namespace App\Filament\Resources\ServiceAreas\Schemas;

use App\Enums\County;
use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        ContentFields::name(label: 'Town'),
                        ContentFields::slug(),
                        Select::make('county')
                            ->options(County::class)
                            ->required(),
                        Toggle::make('is_published')
                            ->label('Show on the website')
                            ->default(true)
                            ->inline(false),
                        ContentFields::richText('intro')
                            ->label('About this town')
                            ->helperText('Write about this town specifically: its homes, its weather, what doors there need. Town pages that read the same get treated as spam by search engines.')
                            ->required(),
                    ]),
                ContentFields::seoSection(),
            ]);
    }
}
