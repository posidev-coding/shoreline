<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Parts\PartResource;
use App\Models\Part;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Stocked parts at or under their reorder point, the emptiest first.
 */
class LowStockParts extends TableWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Part::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low on stock')
            ->query(Part::query()->with('supplier')->active()->lowStock()->orderByRaw('on_hand - reorder_point')->orderBy('name'))
            ->paginated(false)
            ->recordUrl(fn (Part $record): string => PartResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Part')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Part $record): ?string => $record->supplier?->name),
                TextColumn::make('on_hand')
                    ->label('On hand')
                    ->formatStateUsing(fn (string $state, Part $record): string => $record->quantityLabel($state))
                    ->description(fn (Part $record): string => 'Reorder at '.$record->quantityLabel((string) $record->reorder_point))
                    ->color('danger'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Nothing’s low')
            ->emptyStateDescription(null);
    }
}
