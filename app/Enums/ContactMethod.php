<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactMethod: string implements HasLabel
{
    case Call = 'call';
    case Text = 'text';
    case Email = 'email';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Text => 'Text',
            self::Email => 'Email',
        };
    }
}
