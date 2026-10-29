<?php

namespace App\Jobs;

use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Models\User;
use App\Services\QuickBooks\CustomerImporter;
use App\Services\QuickBooks\CustomerMatcher;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports the connected company's customers a page per job, so a long customer list never
 * runs into the queue's 90 seconds: the active ones, then the inactive ones (a plain query
 * returns only active ones). After the last page it matches what it can to clients and tells
 * whoever asked, in the admin's bell, how many are left to review.
 */
class ImportQuickBooksCustomers implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const int PAGE_SIZE = 500;

    public int $timeout = 80;

    public int $tries = 5;

    public function __construct(
        public ?User $requestedBy = null,
        public bool $inactive = false,
        public int $start = 1,
        public int $imported = 0,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new BackOffWhenQuickBooksIsBusy];
    }

    public function handle(CustomerImporter $importer, CustomerMatcher $matcher): void
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        $count = $importer->importPage($connection, $this->inactive, $this->start, self::PAGE_SIZE);
        $imported = $this->imported + $count;

        if ($count === self::PAGE_SIZE) {
            self::dispatch($this->requestedBy, $this->inactive, $this->start + self::PAGE_SIZE, $imported);

            return;
        }

        if (! $this->inactive) {
            self::dispatch($this->requestedBy, true, 1, $imported);

            return;
        }

        $counts = $matcher->matchAll($connection->realm_id);

        if ($this->requestedBy !== null) {
            Notification::make()
                ->success()
                ->title('QuickBooks customers imported')
                ->body("{$imported} imported. {$counts['linked']} matched to clients automatically, {$counts['review']} left to review.")
                ->sendToDatabase($this->requestedBy);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->requestedBy !== null) {
            Notification::make()
                ->danger()
                ->title('QuickBooks customers didn’t import')
                ->body($exception?->getMessage())
                ->sendToDatabase($this->requestedBy);
        }
    }
}
