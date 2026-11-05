<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Support\QuickBooksDocumentColumns;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * QuickBooks invoices, as last pulled. Made and changed in QuickBooks, never here.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client'))
            ->defaultSort('txn_date', 'desc')
            ->columns([
                QuickBooksDocumentColumns::number(),
                QuickBooksDocumentColumns::customer(),
                QuickBooksDocumentColumns::date(),
                QuickBooksDocumentColumns::date('due_date', 'Due'),
                QuickBooksDocumentColumns::amount('total_amt', 'Total'),
                QuickBooksDocumentColumns::amount('balance', 'Balance'),
                TextColumn::make('status')
                    ->state(fn (Invoice $record): InvoiceStatus => $record->status())
                    ->badge(),
            ])
            ->recordActions([
                QuickBooksDocumentColumns::openInQuickBooks(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->emptyStateHeading('No invoices')
            ->emptyStateDescription('Invoices come in from QuickBooks: the last year’s and any still owed when it’s connected, and changes every hour after that.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
