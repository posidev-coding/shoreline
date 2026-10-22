<?php

namespace App\Filament\Resources\Faqs\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('service'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('service.name')
                    ->label('Shows on')
                    ->placeholder('FAQ page'),
                IconColumn::make('is_published')
                    ->label('On site')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('service')->relationship('service', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
