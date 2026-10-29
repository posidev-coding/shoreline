<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where an invoice stands, worked out from its balance, due date, and void flag
 * (App\Models\Invoice::status()). Not stored: it changes with the date.
 */
enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Overdue = 'overdue';
    case PartlyPaid = 'partly_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Overdue => 'Overdue',
            self::PartlyPaid => 'Partly paid',
            self::Paid => 'Paid',
            self::Voided => 'Voided',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Overdue => 'danger',
            self::PartlyPaid => 'warning',
            self::Paid => 'success',
            self::Voided => 'gray',
        };
    }
}
