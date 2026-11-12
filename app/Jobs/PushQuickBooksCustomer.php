<?php

namespace App\Jobs;

use App\Enums\CustomerSyncState;
use App\Enums\SyncIssueKind;
use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Services\QuickBooks\CustomerSync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a linked client's changes to QuickBooks (CustomerSync::push). One queued per client at a
 * time; a change while it runs queues another. Without a connection it leaves the client
 * pending, and the hourly sweep queues it again. Still failing after its retries, it marks the
 * client and raises a sync issue with a Retry button.
 */
class PushQuickBooksCustomer implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 4;

    public function __construct(public int $clientId) {}

    public function uniqueId(): string
    {
        return (string) $this->clientId;
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

    public function handle(CustomerSync $sync): void
    {
        $client = Client::withTrashed()->find($this->clientId);
        $connection = QuickBooksConnection::current();

        if ($client === null || $client->qb_id === null || $connection === null || ! $connection->isConnected()) {
            return;
        }

        $sync->push($connection, $client);
    }

    public function failed(?Throwable $exception): void
    {
        $client = Client::withTrashed()->find($this->clientId);

        if ($client === null || $client->qb_id === null) {
            return;
        }

        CustomerSync::inbound(fn () => $client->forceFill(['qb_sync_state' => CustomerSyncState::Error])->save());

        SyncIssues::raise(
            SyncIssueKind::PushFailed,
            "Changes to {$client->display_name} didn’t reach QuickBooks: ".($exception?->getMessage() ?? 'unknown error'),
            'Customer',
            $client->qb_id,
            $exception instanceof QuickBooksException ? $exception->intuitTid : null,
        );
    }
}
