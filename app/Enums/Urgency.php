<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Urgency: string implements HasColor, HasLabel
{
    case Emergency = 'emergency';
    case Soon = 'soon';
    case Flexible = 'flexible';

    public function getLabel(): string
    {
        return match ($this) {
            self::Emergency => 'Stuck or unsafe right now',
            self::Soon => 'In the next week or two',
            self::Flexible => 'No rush, just planning',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Emergency => 'danger',
            self::Soon => 'warning',
            self::Flexible => 'gray',
        };
    }
}
