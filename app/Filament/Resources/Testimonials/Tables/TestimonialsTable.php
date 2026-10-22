<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('serviceArea'))
            ->defaultSort('reviewed_on', 'desc')
            ->columns([
                TextColumn::make('author_name')
                    ->label('Name')
                    ->searchable()
                    ->description(fn (Testimonial $record): string => (string) $record->serviceArea?->name),
                TextColumn::make('quote')
                    ->label('Review')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('rating')
                    ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('★', $state) : '—'),
                TextColumn::make('source')
                    ->badge(),
                IconColumn::make('is_published')
                    ->label('On site')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('reviewed_on')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
