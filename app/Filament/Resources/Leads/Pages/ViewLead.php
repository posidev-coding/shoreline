<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Lead $record
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markContacted')
                ->label('Mark contacted')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (Lead $record): bool => $record->status === LeadStatus::New)
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::Contacted, 'contacted_at' => now()]);
                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('markSpam')
                ->label('Spam')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('It moves to the Spam tab. Nothing is sent to them.')
                ->visible(fn (Lead $record): bool => $record->status !== LeadStatus::Spam)
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::Spam]);
                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('reopen')
                ->label('Move back to New')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Lead $record): bool => $record->status !== LeadStatus::New)
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::New]);
                    $this->dispatch('refresh-sidebar');
                }),
            DeleteAction::make()
                ->modalDescription('This deletes the request and its photos for good. Use it when someone asks for their details to be erased.'),
        ];
    }
}
