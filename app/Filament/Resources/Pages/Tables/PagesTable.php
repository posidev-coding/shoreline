<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Filament\Support\ContentFields;
use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->recordActions([
                ContentFields::viewOnSite(fn (Page $record): string => route($record->key->routeName())),
                EditAction::make(),
            ]);
    }
}
