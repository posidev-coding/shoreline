<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PartCategory: string implements HasLabel
{
    case Door = 'door';
    case Opener = 'opener';
    case TorsionSpring = 'torsion_spring';
    case ExtensionSpring = 'extension_spring';
    case Cable = 'cable';
    case Drum = 'drum';
    case Roller = 'roller';
    case Track = 'track';
    case Hinge = 'hinge';
    case StrutOrWindBrace = 'strut_or_wind_brace';
    case WeatherSeal = 'weather_seal';
    case Keypad = 'keypad';
    case Remote = 'remote';
    case SafetySensor = 'safety_sensor';
    case SmartController = 'smart_controller';
    case Hardware = 'hardware';

    public function getLabel(): string
    {
        return match ($this) {
            self::Door => 'Door model',
            self::Opener => 'Opener',
            self::TorsionSpring => 'Torsion spring',
            self::ExtensionSpring => 'Extension spring',
            self::Cable => 'Cable',
            self::Drum => 'Drum',
            self::Roller => 'Roller',
            self::Track => 'Track',
            self::Hinge => 'Hinge',
            self::StrutOrWindBrace => 'Strut or wind brace',
            self::WeatherSeal => 'Weather seal',
            self::Keypad => 'Keypad',
            self::Remote => 'Remote',
            self::SafetySensor => 'Safety sensor',
            self::SmartController => 'Smart controller',
            self::Hardware => 'Other hardware',
        };
    }

    /**
     * What a part of this kind becomes once it's installed, or null for parts that wear out
     * and aren't worth tracking at the property, like cables, rollers, and weather seal.
     */
    public function equipmentType(): ?EquipmentType
    {
        return match ($this) {
            self::Door => EquipmentType::Door,
            self::Opener => EquipmentType::Opener,
            self::TorsionSpring, self::ExtensionSpring => EquipmentType::SpringSet,
            self::StrutOrWindBrace => EquipmentType::Hardware,
            self::Keypad => EquipmentType::Keypad,
            self::Remote => EquipmentType::Remote,
            self::SafetySensor => EquipmentType::Sensor,
            self::SmartController => EquipmentType::SmartHub,
            default => null,
        };
    }

    public function isSpring(): bool
    {
        return $this === self::TorsionSpring || $this === self::ExtensionSpring;
    }
}
