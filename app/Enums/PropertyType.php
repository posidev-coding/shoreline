<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropertyType: string implements HasLabel
{
    case Primary = 'primary';
    case VacationRental = 'vacation_rental';
    case LongTermRental = 'long_term_rental';
    case Commercial = 'commercial';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Home',
            self::VacationRental => 'Vacation rental',
            self::LongTermRental => 'Long-term rental',
            self::Commercial => 'Commercial',
        };
    }
}
