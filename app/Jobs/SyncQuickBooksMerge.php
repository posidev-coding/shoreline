<?php

namespace App\Jobs;

use App\Enums\SyncIssueKind;
use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Follows a merge in QuickBooks: one record merged into another. If it still fails after its
 * retries, it becomes a sync issue for a person to check.
 */
class SyncQuickBooksMerge implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 4;

    public function __construct(public string $entity, public string $survivorQbId, public string $mergedQbId) {}

    public function uniqueId(): string
    {
        return "{$this->entity}:{$this->mergedQbId}:{$this->survivorQbId}";
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

        $sync->merge($connection, $this->entity, $this->survivorQbId, $this->mergedQbId);
    }

    public function failed(?Throwable $exception): void
    {
        SyncIssues::raise(
            SyncIssueKind::MergeNeedsReview,
            "QuickBooks merged {$this->entity} {$this->mergedQbId} into {$this->survivorQbId}, and the app couldn’t follow it: ".($exception?->getMessage() ?? 'unknown error'),
            $this->entity,
            $this->mergedQbId,
            $exception instanceof QuickBooksException ? $exception->intuitTid : null,
        );
    }
}
