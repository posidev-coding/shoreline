<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Propaganistas\LaravelPhone\PhoneNumber;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Request')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('service.name')
                                    ->label('Service')
                                    ->placeholder('Something else'),
                                TextEntry::make('urgency')
                                    ->badge(),
                                TextEntry::make('answers')
                                    ->label('Details')
                                    ->state(fn (Lead $record): array => $record->answerLines())
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->placeholder('None')
                                    ->columnSpanFull(),
                                TextEntry::make('message')
                                    ->label('In their words')
                                    ->placeholder('Nothing added')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Photos')
                            ->schema([
                                ViewEntry::make('photos')
                                    ->hiddenLabel()
                                    ->view('filament.leads.photos'),
                            ]),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Contact')
                            ->schema([
                                TextEntry::make('name')
                                    ->weight('semibold'),
                                TextEntry::make('phone')
                                    ->formatStateUsing(fn (string $state): string => (new PhoneNumber($state, 'US'))->formatNational())
                                    ->url(fn (Lead $record): string => 'tel:'.$record->phone)
                                    ->copyable()
                                    ->copyableState(fn (Lead $record): string => $record->phone),
                                TextEntry::make('email')
                                    ->url(fn (Lead $record): string => 'mailto:'.$record->email)
                                    ->copyable(),
                                TextEntry::make('preferred_contact')
                                    ->label('Prefers'),
                                TextEntry::make('sms_consent')
                                    ->label('OK to text')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                                TextEntry::make('address')
                                    ->state(fn (Lead $record): string => "{$record->street}, {$record->town}, NC {$record->zip}")
                                    ->url(fn (Lead $record): string => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode("{$record->street}, {$record->town}, NC {$record->zip}"), shouldOpenInNewTab: true),
                                TextEntry::make('created_at')
                                    ->label('Received')
                                    ->dateTime()
                                    ->since(),
                            ]),
                        Section::make('Earlier requests')
                            ->description('From the same email or phone.')
                            ->visible(fn (Lead $record): bool => $record->earlierRequests()->exists())
                            ->schema([
                                ViewEntry::make('earlier')
                                    ->hiddenLabel()
                                    ->view('filament.leads.earlier-requests'),
                            ]),
                        Section::make('Where they came from')
                            ->collapsed()
                            ->schema([
                                KeyValueEntry::make('attribution')
                                    ->hiddenLabel()
                                    ->keyLabel('')
                                    ->valueLabel('')
                                    ->visible(fn (Lead $record): bool => filled($record->attribution)),
                                TextEntry::make('ip_address')
                                    ->label('IP address')
                                    ->placeholder('Unknown'),
                                TextEntry::make('marketing_consent')
                                    ->label('Storm-season emails')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Opted in' : 'No'),
                            ]),
                    ]),
            ]);
    }
}
