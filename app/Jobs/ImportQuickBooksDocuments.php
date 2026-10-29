<?php

namespace App\Jobs;

use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Models\User;
use App\Services\QuickBooks\DocumentMirror;
use App\Services\QuickBooks\QuickBooksClient;
use App\Support\BusinessCalendar;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Brings in the company's estimates, invoices, and payments from the last year, and any older
 * invoice still owed, a page per job. After that the hourly sweep keeps them current. Older
 * paid documents stay in QuickBooks only.
 */
class ImportQuickBooksDocuments implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Smaller pages than customers': documents carry their lines.
     */
    public const int PAGE_SIZE = 200;

    public const int MONTHS_BACK = 12;

    public int $timeout = 80;

    public int $tries = 5;

    /**
     * @param  string|null  $since  The first date imported (Y-m-d), fixed when the import starts.
     */
    public function __construct(
        public ?User $requestedBy = null,
        public ?string $since = null,
        public int $step = 0,
        public int $start = 1,
        public int $imported = 0,
    ) {
        $this->since ??= BusinessCalendar::today()->subMonths(self::MONTHS_BACK)->toDateString();
    }

    /**
     * What each step asks QuickBooks for. An invoice can come up twice; storing it again only
     * updates it.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function steps(): array
    {
        return [
            ['Estimate', "where TxnDate >= '{$this->since}'"],
            ['Invoice', "where TxnDate >= '{$this->since}'"],
            ['Invoice', "where Balance > '0'"],
            ['Payment', "where TxnDate >= '{$this->since}'"],
        ];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new BackOffWhenQuickBooksIsBusy];
    }

    public function handle(DocumentMirror $mirror): void
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        [$entity, $where] = $this->steps()[$this->step];
        $documents = QuickBooksClient::for($connection)->queryPage($entity, $where, $this->start, self::PAGE_SIZE);

        foreach ($documents as $document) {
            $mirror->store($connection->realm_id, $entity, $document);
        }

        $imported = $this->imported + count($documents);

        if (count($documents) === self::PAGE_SIZE) {
            self::dispatch($this->requestedBy, $this->since, $this->step, $this->start + self::PAGE_SIZE, $imported);
        } elseif ($this->step + 1 < count($this->steps())) {
            self::dispatch($this->requestedBy, $this->since, $this->step + 1, 1, $imported);
        } elseif ($this->requestedBy !== null) {
            Notification::make()
                ->success()
                ->title('QuickBooks estimates, invoices, and payments imported')
                ->body("{$imported} from QuickBooks, dated from ".Carbon::parse($this->since)->format('M j, Y').', plus older invoices still owed.')
                ->sendToDatabase($this->requestedBy);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->requestedBy !== null) {
            Notification::make()
                ->danger()
                ->title('QuickBooks estimates, invoices, and payments didn’t all import')
                ->body($exception?->getMessage())
                ->sendToDatabase($this->requestedBy);
        }
    }
}
