<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Jobs\ImportQuickBooksDocuments;
use App\Models\QuickBooksConnection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'unpaid' => Tab::make('Unpaid')
                ->modifyQueryUsing(fn (Builder $query) => $query->scopes('unpaid')),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'unpaid';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from QuickBooks')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => QuickBooksConnection::current()?->isConnected() === true)
                ->modalDescription('Estimates, invoices, and payments from the last year, and older invoices still owed. Changes come in every hour anyway; this is for catching up after a gap.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ImportQuickBooksDocuments::dispatch($user);

                    Notification::make()->success()->title('Importing estimates, invoices, and payments')->body('The bell says when they’re in.')->send();
                }),
        ];
    }
}
