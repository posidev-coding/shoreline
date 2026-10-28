<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\VisitStatus;
use App\Filament\Resources\Projects\Schemas\VisitForm;
use App\Models\Visit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Trips to the property. Times are entered and shown in the business's time zone.
 */
class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(VisitForm::components());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Visit $record): string => $record->type->getLabel().', '.$record->window())
            ->defaultSort('starts_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('starts_at')
                    ->label('When')
                    ->formatStateUsing(fn (Visit $record): string => $record->window())
                    ->sortable(),
                TextColumn::make('type'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('notes')
                    ->limit(60)
                    ->placeholder('None')
                    ->toggleable(),
                TextColumn::make('client_notified_at')
                    ->label('Client emailed')
                    ->dateTime('M j, g:i A')
                    ->placeholder('No')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Schedule a visit'),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Mark confirmed')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Visit $record): bool => $record->status === VisitStatus::Tentative)
                    ->action(fn (Visit $record) => $record->update(['status' => VisitStatus::Confirmed])),
                Action::make('done')
                    ->label('Mark done')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (Visit $record): bool => $record->status !== VisitStatus::Done && $record->starts_at->isPast())
                    ->action(fn (Visit $record) => $record->update(['status' => VisitStatus::Done])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No visits scheduled')
            ->emptyStateDescription('Schedule a site measure or install with its arrival window.');
    }
}
