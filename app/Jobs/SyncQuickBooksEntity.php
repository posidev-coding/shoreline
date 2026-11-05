<?php

namespace App\Jobs;

use App\Enums\SyncIssueKind;
use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pulls one QuickBooks record as it is now. One queued per record at a time: a second change
 * while it waits adds nothing, since the pull reads the latest version anyway, and a change
 * while it runs queues another. If it still fails after its retries, it becomes a sync issue
 * with a Retry button.
 */
class SyncQuickBooksEntity implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 4;

    public function __construct(public string $entity, public string $qbId) {}

    public function uniqueId(): string
    {
        return "{$this->entity}:{$this->qbId}";
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new BackOffWhenQuickBooksIsBusy];
    }

    public function handle(EntitySync $sync): void
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        $sync->pull($connection, $this->entity, $this->qbId);
    }

    public function failed(?Throwable $exception): void
    {
        SyncIssues::raise(
            SyncIssueKind::PullFailed,
            "QuickBooks {$this->entity} {$this->qbId} didn’t sync: ".($exception?->getMessage() ?? 'unknown error'),
            $this->entity,
            $this->qbId,
            $exception instanceof QuickBooksException ? $exception->intuitTid : null,
        );
    }
}
