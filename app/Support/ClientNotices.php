<?php

namespace App\Support;

use App\Enums\ClientUserRole;
use App\Enums\ProjectStage;
use App\Enums\VisitNoticeKind;
use App\Enums\VisitStatus;
use App\Jobs\SendVisitNotice;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\JobFinished;
use App\Notifications\VisitNotice;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * The emails a client's portal users get about their jobs, and who gets which. Only portal users
 * are emailed: a client without portal access hears from the owner the way they always have.
 * Each email waits a couple of minutes and checks it's still true, so a slip of the mouse on
 * the calendar or the board sends nothing.
 */
final class ClientNotices
{
    public const int DELAY_MINUTES = 2;

    /**
     * @return Collection<int, User>
     */
    public static function messageRecipients(Project $project): Collection
    {
        return self::portalUsers($project, fn (ClientUserRole $role): bool => $role->getsMessageEmails());
    }

    /**
     * @return Collection<int, User>
     */
    public static function jobRecipients(Project $project): Collection
    {
        return self::portalUsers($project, fn (ClientUserRole $role): bool => $role->getsJobEmails());
    }

    /**
     * Whether the client sees the job in the portal at all.
     */
    public static function isVisible(Project $project): bool
    {
        // Loaded, not lazily, in case the project came from a list, where the lazy-loading guard applies.
        $project->loadMissing('client');

        return ! $project->trashed()
            && ! $project->client->trashed()
            && ! in_array($project->stage, ProjectStage::hiddenFromClients(), true);
    }

    /**
     * A confirmed visit, new or changed, is emailed once its time has settled.
     */
    public static function visitSaved(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::Confirmed) {
            return;
        }

        if ($visit->wasRecentlyCreated || $visit->wasChanged(['status', 'starts_at', 'ends_at'])) {
            SendVisitNotice::dispatch($visit)->delay(now()->addMinutes(self::DELAY_MINUTES))->afterCommit();
        }
    }

    /**
     * A visit the client was told about, deleted before it happened, is called off. It's gone,
     * so the email carries what it needs instead of the visit.
     */
    public static function visitDeleted(Visit $visit): void
    {
        $toldStarts = $visit->notified_starts_at;
        $toldEnds = $visit->notified_ends_at;

        if ($toldStarts === null || $toldEnds === null || $visit->status === VisitStatus::Done || $visit->ends_at->isPast()) {
            return;
        }

        $visit->loadMissing('project');

        Notification::send(self::jobRecipients($visit->project), new VisitNotice(
            $visit->project,
            VisitNoticeKind::CalledOff,
            $visit->type->getLabel(),
            Visit::formatWindow($toldStarts, $toldEnds),
        ));
    }

    /**
     * Called when the job's completed_at is newly set.
     */
    public static function jobCompleted(Project $project): void
    {
        if ($project->completed_at === null) {
            return;
        }

        Notification::send(
            self::jobRecipients($project),
            (new JobFinished($project, $project->completed_at))->delay(now()->addMinutes(self::DELAY_MINUTES)),
        );
    }

    /**
     * @param  Closure(ClientUserRole): bool  $wants
     * @return Collection<int, User>
     */
    private static function portalUsers(Project $project, Closure $wants): Collection
    {
        if (! self::isVisible($project)) {
            return collect();
        }

        return $project->client->portalUsers()->get()
            ->filter(fn (User $user): bool => $wants(ClientUser::of($user)->client_role))
            ->values()
            ->toBase();
    }
}
