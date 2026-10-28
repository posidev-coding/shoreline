<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * What a person can do in one client's portal. Every role sees the client's projects and their
 * messages. Estimates and invoices (M6) will gate on these too.
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
            self::Primary => 'The homeowner, or the main contact at a company. Gets every email.',
            self::Manager => 'Someone else who runs the work, like a property manager. Gets every email.',
            self::Billing => 'Someone who handles the bills. Can send messages, but gets no visit emails.',
            self::Viewer => 'Can look, but not reply or approve anything. Gets visit and job-finished emails.',
        };
    }

    public function canSendMessages(): bool
    {
        return $this !== self::Viewer;
    }

    /**
     * Emailed about new messages: the people who can answer them.
     */
    public function getsMessageEmails(): bool
    {
        return $this->canSendMessages();
    }

    /**
     * Emailed when a visit is confirmed, moved, or called off, and when a job is finished: the
     * people following the work.
     */
    public function getsJobEmails(): bool
    {
        return $this !== self::Billing;
    }
}
