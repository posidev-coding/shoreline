<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Used = 'used';
    case Adjusted = 'adjusted';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Used => 'Used on a job',
            self::Adjusted => 'Adjusted',
            self::Returned => 'Returned to stock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received, self::Returned => 'success',
            self::Used => 'primary',
            self::Adjusted => 'gray',
        };
    }
}
