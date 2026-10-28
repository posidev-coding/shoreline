<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which QuickBooks the Intuit app's keys reach: the sandbox's test companies, or real ones.
 */
enum QuickBooksEnvironment: string implements HasLabel
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function configured(): self
    {
        return self::from((string) config('services.quickbooks.environment'));
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://sandbox-quickbooks.api.intuit.com/v3/company/',
            self::Production => 'https://quickbooks.api.intuit.com/v3/company/',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox',
            self::Production => 'Production',
        };
    }
}
