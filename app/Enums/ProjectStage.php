<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a job stands, in the order it normally moves. The board shows one column per open stage.
 * Billing is tracked apart from this (M6), from QuickBooks' invoices and payments.
 */
enum ProjectStage: string implements HasColor, HasLabel
{
    case Estimating = 'estimating';
    case ProposalSent = 'proposal_sent';
    case Approved = 'approved';
    case MaterialsOrdered = 'materials_ordered';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
    case OnHold = 'on_hold';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Estimating => 'Estimating',
            self::ProposalSent => 'Proposal sent',
            self::Approved => 'Approved',
            self::MaterialsOrdered => 'Materials ordered',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Closed => 'Closed',
            self::OnHold => 'On hold',
            self::Lost => 'Lost',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Estimating, self::ProposalSent => 'info',
            self::Approved, self::MaterialsOrdered, self::Scheduled, self::InProgress => 'primary',
            self::Completed, self::Closed => 'success',
            self::OnHold => 'warning',
            self::Lost, self::Cancelled => 'gray',
        };
    }

    /**
     * Still needs the owner's attention: not done, lost, or called off.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, self::finished(), true);
    }

    /**
     * Jobs that never happened, which the client's portal leaves out.
     *
     * @return list<self>
     */
    public static function hiddenFromClients(): array
    {
        return [self::Lost, self::Cancelled];
    }

    /**
     * @return list<self>
     */
    public static function finished(): array
    {
        return [self::Completed, self::Closed, self::Lost, self::Cancelled];
    }
}
