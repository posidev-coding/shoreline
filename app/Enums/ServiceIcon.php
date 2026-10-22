<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The line icon drawn for a service on cards and its page (resources/views/components/site/icon.blade.php).
 */
enum ServiceIcon: string implements HasLabel
{
    case Install = 'install';
    case Repair = 'repair';
    case Spring = 'spring';
    case Opener = 'opener';
    case Wind = 'wind';
    case Toolbox = 'toolbox';

    public function getLabel(): string
    {
        return match ($this) {
            self::Install => 'New door',
            self::Repair => 'Wrench',
            self::Spring => 'Spring',
            self::Opener => 'Opener',
            self::Wind => 'Wind',
            self::Toolbox => 'Toolbox',
        };
    }
}
