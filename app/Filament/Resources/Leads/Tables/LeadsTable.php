<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['service', 'serviceArea']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(['name', 'email', 'phone'])
                    ->weight('semibold')
                    ->description(fn (Lead $record): string => collect([$record->service->name ?? 'Something else', $record->town])->implode(' · ')),
                TextColumn::make('urgency')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No quote requests here')
            ->emptyStateDescription('Requests from the website’s quote form show up here, and the owner gets an email for each one.');
    }
}
