<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Support\QuickBooksDocumentColumns;
use App\Models\Payment;
use App\Models\PaymentApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Payments received in QuickBooks, as last pulled, with the invoices each paid.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'applications.invoice']))
            ->defaultSort('txn_date', 'desc')
            ->columns([
                QuickBooksDocumentColumns::date(),
                QuickBooksDocumentColumns::customer(),
                TextColumn::make('paid')
                    ->label('Paid')
                    ->state(fn (Payment $record): ?string => $record->applications
                        ->map(fn (PaymentApplication $application): string => $application->txn_type === 'Invoice'
                            ? 'Invoice '.($application->invoice->doc_number ?? $application->txn_qb_id)
                            : "{$application->txn_type} {$application->txn_qb_id}")
                        ->implode(', ') ?: null)
                    ->placeholder('Nothing yet')
                    ->wrap(),
                TextColumn::make('reference')
                    ->label('Ref.')
                    ->placeholder('None')
                    ->toggleable(),
                QuickBooksDocumentColumns::amount('total_amt', 'Amount'),
            ])
            ->recordActions([
                QuickBooksDocumentColumns::openInQuickBooks(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('No payments')
            ->emptyStateDescription('Payments come in from QuickBooks: the last year’s when it’s connected, and changes every hour after that.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
