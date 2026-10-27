<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VisitType: string implements HasLabel
{
    case SiteMeasure = 'site_measure';
    case Install = 'install';
    case Service = 'service';
    case Warranty = 'warranty';

    public function getLabel(): string
    {
        return match ($this) {
            self::SiteMeasure => 'Site measure',
            self::Install => 'Install',
            self::Service => 'Service',
            self::Warranty => 'Warranty',
        };
    }
}
