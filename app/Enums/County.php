<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum County: string implements HasLabel
{
    case Carteret = 'carteret';
    case Onslow = 'onslow';
    case Craven = 'craven';

    public function getLabel(): string
    {
        return ucfirst($this->value).' County';
    }
}
