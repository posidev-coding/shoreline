<?php

namespace App\Enums;

/**
 * What a visit email tells the client.
 */
enum VisitNoticeKind: string
{
    case Confirmed = 'confirmed';
    case Moved = 'moved';
    case CalledOff = 'called_off';

    /**
     * The start of the subject line, before the visit and its window.
     */
    public function subjectPrefix(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Moved => 'New time',
            self::CalledOff => 'Called off',
        };
    }
}
