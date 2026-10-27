<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Filament\Support\ContactFields;
use App\Models\Client;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $old) => self::fillDisplayName($get, $set, ['first_name' => $old])),
                        TextInput::make('last_name')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $old) => self::fillDisplayName($get, $set, ['last_name' => $old])),
                        TextInput::make('company')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $old) => self::fillDisplayName($get, $set, ['company' => $old])),
                        self::displayName(),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->requiredWithout('phone')
                            ->validationMessages(['required_without' => 'Add an email or a phone number.']),
                        ContactFields::phone('phone')
                            ->requiredWithout('email')
                            ->validationMessages(['required_without' => 'Add an email or a phone number.']),
                        Toggle::make('is_property_manager')
                            ->label('Property manager')
                            ->helperText('Manages properties for their owners, like a rental company.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Billing address')
                    ->description('Where invoices go. For a homeowner, usually their home.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('billing_street')
                            ->label('Street')
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('billing_city')
                            ->label('City')
                            ->maxLength(255),
                        TextInput::make('billing_zip')
                            ->label('ZIP')
                            ->maxLength(10),
                        TextInput::make('billing_state')
                            ->label('State')
                            ->default('NC')
                            ->length(2),
                    ]),
                Section::make()
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * The name used everywhere, and as their QuickBooks customer name later, so it must be unique.
     */
    public static function displayName(): TextInput
    {
        return TextInput::make('display_name')
            ->label('Display name')
            ->helperText('Shown everywhere, and used as their QuickBooks name later, so each client needs a different one.')
            ->required()
            ->maxLength(255)
            // The record is the client being edited, or something else (a quote request) when converting one.
            ->rule(fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                if (Client::displayNameTaken((string) $value, $record instanceof Client ? $record->getKey() : null)) {
                    $fail('Another client, maybe one in the trash, already has this name. Add something to tell them apart, like the town.');
                }
            });
    }

    /**
     * Fills the display name from the person's name, or their company without one, and keeps it
     * in step as those change, until someone types a display name of their own.
     *
     * @param  array<string, ?string>  $before  The field that just changed, with its value before.
     */
    private static function fillDisplayName(Get $get, Set $set, array $before): void
    {
        $nameFrom = function (array $fields): string {
            $name = trim($fields['first_name'].' '.$fields['last_name']);

            return $name !== '' ? $name : trim((string) $fields['company']);
        };

        $now = ['first_name' => $get('first_name'), 'last_name' => $get('last_name'), 'company' => $get('company')];
        $current = (string) $get('display_name');

        if ($current !== '' && $current !== $nameFrom([...$now, ...$before])) {
            return;
        }

        $set('display_name', $nameFrom($now));
    }
}
