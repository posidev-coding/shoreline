<?php

namespace App\Filament\Resources\ServiceAreas\Tables;

use App\Enums\County;
use App\Filament\Support\ContentFields;
use App\Models\ServiceArea;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Town')
                    ->searchable(),
                TextColumn::make('county')
                    ->badge(),
                TextColumn::make('showcases_count')
                    ->counts('showcases')
                    ->label('Jobs shown'),
                TextColumn::make('testimonials_count')
                    ->counts('testimonials')
                    ->label('Reviews'),
                IconColumn::make('is_published')
                    ->label('On site')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('county')->options(County::class),
            ])
            ->recordActions([
                ContentFields::viewOnSite(fn (ServiceArea $record): string => route('areas.show', $record)),
                EditAction::make(),
            ]);
    }
}
