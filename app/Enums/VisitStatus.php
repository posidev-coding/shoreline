<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisitStatus: string implements HasColor, HasLabel
{
    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tentative => 'Tentative',
            self::Confirmed => 'Confirmed',
            self::Done => 'Done',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tentative => 'warning',
            self::Confirmed => 'primary',
            self::Done => 'success',
        };
    }
}
