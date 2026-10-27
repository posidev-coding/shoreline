<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\ProjectUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The project's timeline: notes on what happened. Shared entries are what the client
 * will see in their portal (M4).
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Timeline';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Update')
                    ->required()
                    ->rows(3),
                Toggle::make('is_visible_to_client')
                    ->label('Share with the client')
                    ->helperText('Shared updates will show in their portal, once it opens. The rest stay your own notes.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->columns([
                TextColumn::make('body')
                    ->label('Update')
                    ->wrap()
                    ->description(fn (ProjectUpdate $record): string => collect([
                        $record->author?->name,
                        $record->created_at?->timezone(config('app.business_timezone'))->format('M j, g:i A'),
                    ])->filter()->implode(' · ')),
                IconColumn::make('is_visible_to_client')
                    ->label('Shared')
                    ->boolean()
                    ->width('1%'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add update')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No updates yet');
    }
}
