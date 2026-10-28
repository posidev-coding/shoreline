<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * What a person can do in one client's portal. Every role sees the client's projects; the rest
 * of each role's meaning arrives with what it gates (messages in M4, estimates and invoices in M6).
 */
enum ClientUserRole: string implements HasDescription, HasLabel
{
    case Primary = 'primary';
    case Manager = 'manager';
    case Billing = 'billing';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Primary contact',
            self::Manager => 'Manager',
            self::Billing => 'Billing',
            self::Viewer => 'Viewer',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Primary => 'The homeowner, or the main contact at a company.',
            self::Manager => 'Someone else who runs the work, like a property manager.',
            self::Billing => 'Someone who handles the bills.',
            self::Viewer => 'Can look, but not reply or approve anything.',
        };
    }
}
