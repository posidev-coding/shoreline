<?php

namespace App\Filament\Resources\Parts\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Part;
use App\Models\PartStockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * The part's stock history, newest first. Read only: changes go through the page's stock actions.
 *
 * @property Part $ownerRecord
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Stock history';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Part && ($ownerRecord->track_stock || $ownerRecord->movements()->exists());
    }

    #[On('refresh-movements')]
    public function refreshMovements(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['project', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A'),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Change')
                    ->formatStateUsing(fn (string $state, PartStockMovement $record): string => ((float) $state > 0 ? '+' : '').$this->ownerRecord->quantityLabel($state)),
                TextColumn::make('balance_after')
                    ->label('On hand after')
                    ->formatStateUsing(fn (string $state): string => $this->ownerRecord->quantityLabel($state)),
                TextColumn::make('project.title')
                    ->label('Job')
                    ->url(fn (PartStockMovement $record): ?string => $record->project_id ? ProjectResource::getUrl('view', ['record' => $record->project_id]) : null)
                    ->placeholder('None'),
                TextColumn::make('note')
                    ->description(fn (PartStockMovement $record): ?string => $record->user?->name)
                    ->placeholder('None')
                    ->wrap(),
            ])
            ->emptyStateHeading('No stock changes yet')
            ->emptyStateDescription('Receive or count stock from the buttons above.');
    }
}
