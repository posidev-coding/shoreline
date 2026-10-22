<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Client = 'client';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Owner => 'Owner',
            self::Client => 'Client',
        };
    }

    public function canAccessAdmin(): bool
    {
        return $this !== self::Client;
    }
}
