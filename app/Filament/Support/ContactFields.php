<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Form fields shared by the client and property resources.
 */
final class ContactFields
{
    /**
     * A US phone number, shown as (252) 555-0142 and stored as E.164, as the quote form stores it,
     * so the same person's numbers match.
     */
    public static function phone(string $name, string $label = 'Phone'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->tel()
            ->rule('phone:US')
            ->formatStateUsing(fn (?string $state): ?string => self::national($state))
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? (new PhoneNumber($state, 'US'))->formatE164() : null);
    }

    /**
     * (252) 555-0142, or the value as it is when it isn't a number we can read.
     */
    public static function national(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        try {
            return (new PhoneNumber($phone, 'US'))->formatNational();
        } catch (Throwable) {
            return $phone;
        }
    }
}
