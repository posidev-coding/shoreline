<?php

namespace App\Services\QuickBooks;

use App\Enums\SyncIssueKind;
use App\Enums\UserRole;
use App\Filament\Resources\QuickBooksSyncIssues\QuickBooksSyncIssueResource;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Raises and resolves QuickBooks sync issues. The same problem raised again counts another
 * occurrence on its open issue, so super admins hear about it once, in the bell.
 */
final class SyncIssues
{
    public static function raise(SyncIssueKind $kind, string $message, ?string $entity = null, ?string $qbId = null, ?string $intuitTid = null): QuickBooksSyncIssue
    {
        $issue = QuickBooksSyncIssue::query()->open()
            ->where('kind', $kind)
            ->where('entity', $entity)
            ->where('qb_id', $qbId)
            ->first();

        if ($issue !== null) {
            $issue->forceFill([
                'message' => $message,
                'intuit_tid' => $intuitTid ?? $issue->intuit_tid,
                'occurrences' => $issue->occurrences + 1,
                'last_seen_at' => now(),
            ])->save();

            return $issue;
        }

        $issue = QuickBooksSyncIssue::query()->create([
            'realm_id' => QuickBooksConnection::current()?->realm_id,
            'kind' => $kind,
            'entity' => $entity,
            'qb_id' => $qbId,
            'message' => $message,
            'intuit_tid' => $intuitTid,
            'last_seen_at' => now(),
        ]);

        Notification::make()
            ->danger()
            ->title('QuickBooks sync issue: '.$kind->getLabel())
            ->body($message)
            ->actions([Action::make('view')->label('Sync issues')->url(QuickBooksSyncIssueResource::getUrl(panel: 'admin'))])
            ->sendToDatabase(User::query()->where('role', UserRole::SuperAdmin)->get());

        return $issue;
    }

    public static function resolve(QuickBooksSyncIssue $issue, ?User $by): void
    {
        $issue->forceFill(['resolved_at' => now(), 'resolved_by' => $by?->getKey()])->save();
    }

    /**
     * Resolves the open issue about a record that has now synced, if there is one.
     */
    public static function resolveFor(string $entity, string $qbId): void
    {
        QuickBooksSyncIssue::query()->open()
            ->where('kind', SyncIssueKind::PullFailed)
            ->where('entity', $entity)
            ->where('qb_id', $qbId)
            ->update(['resolved_at' => now()]);
    }
}
