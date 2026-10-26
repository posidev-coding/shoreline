<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReviewSource: string implements HasLabel
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Nextdoor = 'nextdoor';
    case Direct = 'direct';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
            self::Nextdoor => 'Nextdoor',
            self::Direct => 'Sent to us directly',
            self::Other => 'Other',
        };
    }

    /**
     * How a review's source reads under the quote: "via Google", or "Sent to us directly".
     */
    public function attribution(): string
    {
        return match ($this) {
            self::Direct, self::Other => $this->getLabel(),
            default => 'via '.$this->getLabel(),
        };
    }
}
