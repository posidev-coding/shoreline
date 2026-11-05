<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a linked client stands with its QuickBooks customer.
 */
enum CustomerSyncState: string implements HasColor, HasLabel
{
    case Clean = 'clean';
    /** A change waiting to go to QuickBooks. */
    case Pending = 'pending';
    /** A change that didn't reach QuickBooks after its retries; a sync issue says why. */
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Clean => 'In sync',
            self::Pending => 'Sending changes to QuickBooks',
            self::Error => 'Changes didn’t reach QuickBooks',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Clean => 'success',
            self::Pending => 'info',
            self::Error => 'danger',
        };
    }
}
