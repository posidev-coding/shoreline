<?php

namespace App\Jobs;

use App\Models\QuickBooksWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Turns one webhook delivery's events into pulls: each record the app follows is pulled as it
 * is now, and a merge is followed through. Calls nothing itself, so the delivery is answered
 * quickly however many events it carries.
 */
class ProcessQuickBooksWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(public array $eventIds) {}

    public function handle(): void
    {
        foreach (QuickBooksWebhookEvent::query()->whereKey($this->eventIds)->orderBy('id')->get() as $event) {
            $entity = $event->entity();

            if ($entity === null || $event->qb_id === null) {
                continue;
            }

            $merged = $event->mergedQbId();

            if ($merged !== null) {
                SyncQuickBooksMerge::dispatch($entity, $event->qb_id, $merged);
            } else {
                SyncQuickBooksEntity::dispatch($entity, $event->qb_id);
            }
        }
    }
}
