<?php

namespace App\Filament\Support;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Usd;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;

/**
 * Columns and the link to QuickBooks, shared by the estimate, invoice, and payment lists and the
 * client page's tabs.
 */
final class QuickBooksDocumentColumns
{
    public static function number(): TextColumn
    {
        return TextColumn::make('doc_number')
            ->label('No.')
            ->searchable()
            ->sortable()
            ->placeholder('None');
    }

    /**
     * The QuickBooks customer, and the client it's linked to, except on that client's own page.
     */
    public static function customer(): TextColumn
    {
        return TextColumn::make('customer_name')
            ->label('Customer')
            ->searchable()
            ->wrap()
            ->description(fn (Estimate|Invoice|Payment $record, HasTable $livewire): ?string => match (true) {
                $livewire instanceof RelationManager => null,
                $record->client !== null => "Client: {$record->client->display_name}",
                default => 'Not linked to a client',
            })
            ->url(fn (Estimate|Invoice|Payment $record, HasTable $livewire): ?string => $record->client && ! $livewire instanceof RelationManager
                ? ClientResource::getUrl('view', ['record' => $record->client])
                : null);
    }

    public static function date(string $name = 'txn_date', string $label = 'Date'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->date('M j, Y')
            ->sortable()
            ->placeholder('None');
    }

    public static function amount(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (?string $state): string => $state === null ? '' : Usd::amount($state))
            ->alignEnd()
            ->sortable();
    }

    public static function openInQuickBooks(): Action
    {
        return Action::make('openInQuickBooks')
            ->label('QuickBooks')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (Estimate|Invoice|Payment $record): ?string => $record->quickBooksUrl())
            ->openUrlInNewTab();
    }
}
