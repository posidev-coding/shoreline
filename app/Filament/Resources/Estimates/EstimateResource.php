<?php

namespace App\Filament\Resources\Estimates;

use App\Enums\EstimateStatus;
use App\Filament\Resources\Estimates\Pages\ListEstimates;
use App\Filament\Support\QuickBooksDocumentColumns;
use App\Models\Estimate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * QuickBooks estimates, as last pulled. Made and changed in QuickBooks, never here, until the
 * app sends estimates for jobs (M6).
 */
class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client'))
            ->defaultSort('txn_date', 'desc')
            ->columns([
                QuickBooksDocumentColumns::number(),
                QuickBooksDocumentColumns::customer(),
                QuickBooksDocumentColumns::date(),
                QuickBooksDocumentColumns::date('expiration_date', 'Expires'),
                QuickBooksDocumentColumns::amount('total_amt', 'Total'),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Estimate $record): ?string => $record->accepted_by ? "By {$record->accepted_by}" : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EstimateStatus::class),
            ])
            ->recordActions([
                QuickBooksDocumentColumns::openInQuickBooks(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('No estimates')
            ->emptyStateDescription('Estimates come in from QuickBooks: the last year’s when it’s connected, and changes every hour after that.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstimates::route('/'),
        ];
    }
}
