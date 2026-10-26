<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\ProjectTask;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The project's checklist.
 *
 * @property Project $ownerRecord
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Checklist';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Task')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort_order')
            ->paginated(false)
            ->columns([
                CheckboxColumn::make('is_done')
                    ->label('')
                    ->state(fn (ProjectTask $record): bool => $record->completed_at !== null)
                    ->updateStateUsing(function (ProjectTask $record, bool $state): bool {
                        $record->update(['completed_at' => $state ? now() : null]);

                        return $state;
                    })
                    ->width('1%'),
                TextColumn::make('title')
                    ->label('Task')
                    ->color(fn (ProjectTask $record): ?string => $record->completed_at ? 'gray' : null)
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add task')
                    ->createAnother()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'sort_order' => (int) $this->ownerRecord->tasks()->max('sort_order') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nothing on the checklist');
    }
}
