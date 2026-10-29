<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Filament\Resources\QuickBooksCustomers\QuickBooksCustomerResource;
use App\Filament\Support\ContactFields;
use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Contact')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')
                            ->formatStateUsing(fn (string $state): ?string => ContactFields::national($state))
                            ->url(fn (Client $record): ?string => filled($record->phone) ? 'tel:'.$record->phone : null)
                            ->placeholder('None'),
                        TextEntry::make('email')
                            ->url(fn (Client $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                            ->copyable()
                            ->placeholder('None'),
                        TextEntry::make('name')
                            ->state(fn (Client $record): string => trim($record->first_name.' '.$record->last_name))
                            ->placeholder('None'),
                        TextEntry::make('company')
                            ->placeholder('None'),
                        TextEntry::make('notes')
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),
                Section::make('Billing')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('billing_address')
                            ->label('Address')
                            ->state(fn (Client $record): ?string => filled($record->billing_street)
                                ? "{$record->billing_street}, {$record->billing_city}, {$record->billing_state} {$record->billing_zip}"
                                : null)
                            ->placeholder('None'),
                        TextEntry::make('is_property_manager')
                            ->label('Property manager')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('created_at')
                            ->label('Client since')
                            ->date(),
                        TextEntry::make('qb_id')
                            ->label('QuickBooks')
                            ->state(fn (Client $record): ?string => $record->qb_id === null ? null : ($record->quickBooksCustomer->display_name ?? "Customer {$record->qb_id}"))
                            ->helperText(fn (Client $record): ?string => $record->qb_linked_at ? 'Linked '.$record->qb_linked_at->timezone(config('app.business_timezone'))->format('M j, Y') : null)
                            ->url(fn (Client $record): ?string => $record->quickBooksCustomer
                                ? QuickBooksCustomerResource::getUrl(parameters: ['tab' => 'linked', 'search' => $record->quickBooksCustomer->display_name])
                                : null)
                            ->placeholder('Not linked'),
                    ]),
            ]);
    }
}
