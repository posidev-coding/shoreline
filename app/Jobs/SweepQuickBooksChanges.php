<?php

namespace App\Jobs;

use App\Enums\SyncIssueKind;
use App\Jobs\Middleware\BackOffWhenQuickBooksIsBusy;
use App\Models\QuickBooksConnection;
use App\Services\QuickBooks\CustomerMatcher;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Hourly: asks QuickBooks for every customer, item, estimate, invoice, and payment changed since
 * the last check, and brings them in. It's how the app keeps up without webhooks, and the safety
 * net for any webhook missed while the app was asleep. A record that won't store becomes a sync
 * issue without holding up the rest.
 */
class SweepQuickBooksChanges implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Each check reaches this far behind the last one, for changes saved as it ran.
     */
    public const int OVERLAP_MINUTES = 5;

    /**
     * QuickBooks reports changes up to 30 days back, and at most 1,000 in one answer.
     */
    public const int LOOKBACK_DAYS = 30;

    public const int MAX_CHANGES = 1000;

    public int $timeout = 85;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new BackOffWhenQuickBooksIsBusy];
    }

    public function handle(EntitySync $sync, CustomerMatcher $matcher): void
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        $since = $this->since($connection);
        $askedAt = now();
        ['time' => $time, 'changes' => $changes] = QuickBooksClient::for($connection)
            ->changesSince(EntitySync::ENTITIES, $since->copy()->subMinutes(self::OVERLAP_MINUTES));
        $count = 0;

        foreach (EntitySync::ENTITIES as $entity) {
            foreach ($changes[$entity] ?? [] as $record) {
                $count++;

                try {
                    $sync->apply($connection, $entity, $record);
                } catch (Throwable $exception) {
                    report($exception);
                    $qbId = (string) ($record['Id'] ?? '');
                    SyncIssues::raise(SyncIssueKind::PullFailed, "QuickBooks {$entity} {$qbId} didn’t sync: {$exception->getMessage()}", $entity, $qbId);
                }
            }
        }

        if ($count >= self::MAX_CHANGES) {
            SyncIssues::raise(SyncIssueKind::TooManyChanges, "QuickBooks reported {$count} changes since ".self::shown($since).', the most it gives at once, so some may be missing. Import items, customers, and invoices again from their pages.');
        }

        // A new customer may be someone the app already knows.
        if (($changes['Customer'] ?? []) !== []) {
            $matcher->matchAll($connection->realm_id);
        }

        $connection->update(['changes_synced_at' => $time ?? $askedAt]);
    }

    public function failed(?Throwable $exception): void
    {
        SyncIssues::raise(
            SyncIssueKind::SweepFailed,
            'The hourly check for QuickBooks changes failed: '.($exception?->getMessage() ?? 'unknown error').'. The next check starts from the same point.',
            intuitTid: $exception instanceof QuickBooksException ? $exception->intuitTid : null,
        );
    }

    /**
     * Where this check starts: the last one, or, when that's beyond what QuickBooks reports,
     * as far back as it goes, with a sync issue saying what was missed.
     */
    private function since(QuickBooksConnection $connection): Carbon
    {
        $since = $connection->changes_synced_at ?? $connection->connected_at ?? now();
        $earliest = now()->subDays(self::LOOKBACK_DAYS)->addHour();

        if ($since->gte($earliest)) {
            return $since;
        }

        SyncIssues::raise(SyncIssueKind::ChangesMissed, 'The app last checked QuickBooks for changes on '.self::shown($since).', more than '.self::LOOKBACK_DAYS.' days ago, and QuickBooks only reports '.self::LOOKBACK_DAYS.' days back. Changes before '.self::shown($earliest).' are missing: import items, customers, and invoices again from their pages.');

        return $earliest;
    }

    private static function shown(Carbon $moment): string
    {
        return $moment->copy()->timezone(config('app.business_timezone'))->format('M j, Y g:i A');
    }
}
