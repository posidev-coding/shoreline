<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The fixed set of copy pages the owner edits. Each has its own route, so pages
 * can be edited but not created or deleted.
 */
enum PageKey: string implements HasLabel
{
    case About = 'about';
    case HurricaneReady = 'hurricane-ready';
    case Privacy = 'privacy';
    case Terms = 'terms';

    public function getLabel(): string
    {
        return match ($this) {
            self::About => 'About',
            self::HurricaneReady => 'Hurricane-Ready',
            self::Privacy => 'Privacy Policy',
            self::Terms => 'Terms of Service',
        };
    }

    public function routeName(): string
    {
        return 'pages.'.$this->value;
    }
}
