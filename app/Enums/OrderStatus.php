<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a line item's part stands when it has to be ordered for the job. A line with nothing
 * to order has no status.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case ToOrder = 'to_order';
    case Ordered = 'ordered';
    case Received = 'received';

    public function getLabel(): string
    {
        return match ($this) {
            self::ToOrder => 'To order',
            self::Ordered => 'Ordered',
            self::Received => 'Received',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ToOrder => 'warning',
            self::Ordered => 'info',
            self::Received => 'success',
        };
    }
}
