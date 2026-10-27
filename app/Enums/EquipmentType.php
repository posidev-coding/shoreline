<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EquipmentType: string implements HasLabel
{
    case Door = 'door';
    case Opener = 'opener';
    case SpringSet = 'spring_set';
    case Hardware = 'hardware';
    case Keypad = 'keypad';
    case Remote = 'remote';
    case Sensor = 'sensor';
    case SmartHub = 'smart_hub';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Door => 'Door',
            self::Opener => 'Opener',
            self::SpringSet => 'Spring set',
            self::Hardware => 'Hardware',
            self::Keypad => 'Keypad',
            self::Remote => 'Remote',
            self::Sensor => 'Safety sensors',
            self::SmartHub => 'Smart hub',
            self::Other => 'Other',
        };
    }
}
