<?php

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Jobs\ImportQuickBooksItems;
use App\Models\QuickBooksConnection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageCatalogItems extends ManageRecords
{
    protected static string $resource = CatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from QuickBooks')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => QuickBooksConnection::current()?->isConnected() === true)
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ImportQuickBooksItems::dispatch($user);

                    Notification::make()->success()->title('Importing items from QuickBooks')->body('The bell says when they’re in. Refresh the page to see them.')->send();
                }),
        ];
    }
}
