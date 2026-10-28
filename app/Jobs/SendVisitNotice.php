<?php

namespace App\Jobs;

use App\Enums\VisitNoticeKind;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Notifications\VisitNotice;
use App\Support\ClientNotices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the client about a confirmed visit, a couple of minutes after it was confirmed or moved,
 * if it still is and its window isn't the one they were last told. Several quick changes send
 * one email, and moving a visit back before the email goes sends none.
 */
#[DeleteWhenMissingModels]
class SendVisitNotice implements ShouldQueue
{
    use Queueable;

    public function __construct(public Visit $visit) {}

    public function handle(): void
    {
        $visit = $this->visit;

        if ($visit->status !== VisitStatus::Confirmed || $visit->ends_at->isPast()) {
            return;
        }

        $wasTold = $visit->notified_starts_at !== null && $visit->notified_ends_at !== null;

        if ($wasTold && $visit->notified_starts_at->equalTo($visit->starts_at) && $visit->notified_ends_at->equalTo($visit->ends_at)) {
            return;
        }

        $recipients = ClientNotices::jobRecipients($visit->project);

        if ($recipients->isEmpty()) {
            return;
        }

        // Already on the queue, so the emails go now, and the window is recorded only once they have.
        Notification::sendNow($recipients, new VisitNotice(
            $visit->project,
            $wasTold ? VisitNoticeKind::Moved : VisitNoticeKind::Confirmed,
            $visit->type->getLabel(),
            $visit->window(),
            $wasTold ? Visit::formatWindow($visit->notified_starts_at, $visit->notified_ends_at) : null,
        ));

        // Not through the model: saving it would schedule this job again and log an edit nobody made.
        Visit::query()->whereKey($visit->getKey())->toBase()->update([
            'client_notified_at' => now(),
            'notified_starts_at' => $visit->starts_at,
            'notified_ends_at' => $visit->ends_at,
        ]);
    }
}
