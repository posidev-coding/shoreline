<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EquipmentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Removed = 'removed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'In place',
            self::Replaced => 'Replaced',
            self::Removed => 'Removed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Replaced, self::Removed => 'gray',
        };
    }
}
