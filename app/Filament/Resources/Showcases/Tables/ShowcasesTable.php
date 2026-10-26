<?php

namespace App\Filament\Resources\Showcases\Tables;

use App\Filament\Support\ContentFields;
use App\Models\Showcase;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShowcasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['media', 'service', 'serviceArea']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('after')
                    ->collection('after')
                    ->conversion('w640')
                    ->label('')
                    ->imageHeight(56),
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (Showcase $record): string => collect([$record->service?->name, $record->serviceArea?->name])->filter()->implode(' · ')),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Showcase $record): string => match (true) {
                        $record->isPublic() => 'Published',
                        ! $record->has_photo_release => 'Needs photo release',
                        $record->published_at !== null => 'Scheduled',
                        default => 'Draft',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Published' => 'success',
                        'Needs photo release' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                ContentFields::viewOnSite(fn (Showcase $record): string => route('work.show', $record))
                    ->visible(fn (Showcase $record): bool => $record->isPublic()),
                EditAction::make(),
            ]);
    }
}
