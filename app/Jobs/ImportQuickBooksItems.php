<?php

namespace App\Jobs;

use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Models\User;
use App\Services\QuickBooks\ItemImporter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports the connected company's items, and tells whoever asked how it went, in the admin's
 * bell. A few hundred items take a few requests, well inside the queue's 90 seconds.
 */
class ImportQuickBooksItems implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 5;

    public function __construct(public ?User $requestedBy = null) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new BackOffWhenQuickBooksIsBusy];
    }

    public function handle(ItemImporter $importer): void
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        $counts = $importer->import($connection);

        if ($this->requestedBy !== null) {
            Notification::make()
                ->success()
                ->title('QuickBooks items imported')
                ->body("{$counts['created']} new, {$counts['updated']} updated.")
                ->sendToDatabase($this->requestedBy);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->requestedBy !== null) {
            Notification::make()
                ->danger()
                ->title('QuickBooks items didn’t import')
                ->body($exception?->getMessage())
                ->sendToDatabase($this->requestedBy);
        }
    }
}
