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

    public function getLabel(): string
    {
        return match ($this) {
            self::PullFailed => 'Couldn’t pull a record',
            self::SweepFailed => 'Change check failed',
            self::ChangesMissed => 'Changes missed',
            self::TooManyChanges => 'Too many changes',
            self::CustomerDeleted => 'Linked customer gone',
        };
    }

    /**
     * Whether pulling the record again might fix it.
     */
    public function isRetryable(): bool
    {
        return $this === self::PullFailed;
    }
}
