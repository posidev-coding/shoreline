<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An estimate's TxnStatus in QuickBooks. Closed is what QuickBooks shows once an estimate has
 * become an invoice.
 */
enum EstimateStatus: string implements HasColor, HasLabel
{
    case Pending = 'Pending';
    case Accepted = 'Accepted';
    case Closed = 'Closed';
    case Rejected = 'Rejected';
    /** Any status QuickBooks adds later; its raw status stays in qb_payload. */
    case Other = 'Other';

    /**
     * QuickBooks leaves TxnStatus out of a new estimate, which it shows as Pending.
     */
    public static function fromQuickBooks(mixed $status): self
    {
        return $status === null ? self::Pending : (self::tryFrom((string) $status) ?? self::Other);
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Accepted => 'success',
            self::Closed, self::Other => 'gray',
            self::Rejected => 'danger',
        };
    }
}
