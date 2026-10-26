<?php

namespace App\Filament\Resources\Services\Tables;

use App\Filament\Support\ContentFields;
use App\Models\Service;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Service $record): string => $record->summary),
                TextColumn::make('faqs_count')
                    ->counts('faqs')
                    ->label('FAQs'),
                IconColumn::make('is_published')
                    ->label('On site')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ContentFields::viewOnSite(fn (Service $record): string => route('services.show', $record)),
                EditAction::make(),
            ]);
    }
}
