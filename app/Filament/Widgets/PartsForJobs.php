<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Pages\PartsToOrder;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectLineItem;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Parts bought for jobs that haven't arrived: still to order first, then what's on the way by
 * when it's due.
 */
class PartsForJobs extends TableWidget
{
    /**
     * Rows shown; Parts to order has the rest.
     */
    public const int LIMIT = 5;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', ProjectLineItem::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Parts for jobs')
            ->query(ProjectLineItem::query()
                ->withoutTrashedProjects()
                ->with('project')
                ->whereIn('order_status', [OrderStatus::ToOrder, OrderStatus::Ordered])
                ->orderByRaw('order_status = ? desc', [OrderStatus::ToOrder->value])
                ->orderByRaw('expected_on nulls last')
                ->limit(self::LIMIT))
            ->paginated(false)
            ->recordUrl(fn (ProjectLineItem $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id]))
            ->columns([
                TextColumn::make('description')
                    ->label('Part')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (ProjectLineItem $record): string => $record->project->title),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (ProjectLineItem $record): ?string => $record->expected_on ? 'Due '.$record->expected_on->format('M j') : null),
            ])
            ->headerActions([
                Action::make('partsToOrder')
                    ->label('Parts to order')
                    ->link()
                    ->url(PartsToOrder::getUrl()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Nothing on order')
            ->emptyStateDescription(null);
    }
}
