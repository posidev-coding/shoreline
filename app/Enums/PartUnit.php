<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PartUnit: string implements HasLabel
{
    case Each = 'each';
    case Pair = 'pair';
    case Set = 'set';
    case Foot = 'foot';

    public function getLabel(): string
    {
        return match ($this) {
            self::Each => 'Each',
            self::Pair => 'Pair',
            self::Set => 'Set',
            self::Foot => 'Foot',
        };
    }
}
