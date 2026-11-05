<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What kind of QuickBooks sync problem a person needs to look at.
 */
enum SyncIssueKind: string implements HasLabel
{
    /** A record couldn't be fetched or stored, even after retrying. Retry pulls it again. */
    case PullFailed = 'pull_failed';
    /** The hourly check for changes failed. The next one picks up from the same point. */
    case SweepFailed = 'sweep_failed';
    /** The check fell more than 30 days behind, beyond what QuickBooks reports. */
    case ChangesMissed = 'changes_missed';
    /** More changes than one check returns (1,000), so some may be missing. */
    case TooManyChanges = 'too_many_changes';
    /** A customer linked to a client was deleted or merged away in QuickBooks. */
    case CustomerDeleted = 'customer_deleted';
    /** A client's change that didn't reach QuickBooks, even after retrying. Retry sends it again. */
    case PushFailed = 'push_failed';
    /** A client changed in both places since the last sync; this says which values were kept. */
    case CustomerConflict = 'customer_conflict';
    /** A name QuickBooks already has, on a customer, vendor, or employee. */
    case NameTaken = 'name_taken';
    /** A merge in QuickBooks that the app couldn't follow by itself. */
    case MergeNeedsReview = 'merge_needs_review';
    /** A signed webhook the app couldn't read. */
    case WebhookUnreadable = 'webhook_unreadable';

    public function getLabel(): string
    {
        return match ($this) {
            self::PullFailed => 'Couldn’t pull a record',
            self::SweepFailed => 'Change check failed',
            self::ChangesMissed => 'Changes missed',
            self::TooManyChanges => 'Too many changes',
            self::CustomerDeleted => 'Linked customer gone',
            self::PushFailed => 'Couldn’t send changes',
            self::CustomerConflict => 'Changed in both places',
            self::NameTaken => 'Name already in QuickBooks',
            self::MergeNeedsReview => 'Merge to check',
            self::WebhookUnreadable => 'Unreadable webhook',
        };
    }

    /**
     * Whether pulling or sending the record again might fix it.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::PullFailed, self::PushFailed], true);
    }
}
