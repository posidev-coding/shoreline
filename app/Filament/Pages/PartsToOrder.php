<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectLineItem;
use BackedEnum;
use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Every part bought for a job that hasn't arrived yet, grouped by supplier, so one call or
 * one order covers everything a supplier owes.
 */
class PartsToOrder extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Parts';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Parts to order';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', ProjectLineItem::class) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ProjectLineItem::query()->withoutTrashedProjects()->where('order_status', OrderStatus::ToOrder)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Still to order';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProjectLineItem::query()
                ->whereIn('order_status', [OrderStatus::ToOrder, OrderStatus::Ordered])
                ->withoutTrashedProjects()
                ->with(['project.client', 'supplier', 'part']))
            ->defaultGroup(Group::make('supplier.name')
                ->label('Supplier')
                ->getTitleFromRecordUsing(fn (ProjectLineItem $record): string => $record->supplier->name ?? 'No supplier picked'))
            ->defaultSort('expected_on')
            ->columns([
                TextColumn::make('description')
                    ->label('Part')
                    ->wrap()
                    ->description(fn (ProjectLineItem $record): ?string => $record->part?->sku),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn (string $state, ProjectLineItem $record): string => $record->part?->quantityLabel($state) ?? (string) BigDecimal::of($state)->strippedOfTrailingZeros()),
                TextColumn::make('project.title')
                    ->label('Job')
                    ->description(fn (ProjectLineItem $record): string => $record->project->client->display_name)
                    ->url(fn (ProjectLineItem $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id])),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('expected_on')
                    ->label('Expected')
                    ->date('M j')
                    ->color(fn (ProjectLineItem $record): ?string => $record->expected_on?->isPast() ? 'danger' : null)
                    ->placeholder('Not set')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Status')
                    ->options([OrderStatus::ToOrder->value => 'To order', OrderStatus::Ordered->value => 'Ordered, on the way']),
            ])
            ->recordActions([
                Action::make('markOrdered')
                    ->label('Mark ordered')
                    ->icon(Heroicon::OutlinedTruck)
                    ->visible(fn (ProjectLineItem $record): bool => $record->order_status === OrderStatus::ToOrder)
                    ->schema([DatePicker::make('expected_on')->label('Expected')->native(false)])
                    ->modalWidth('sm')
                    ->action(fn (ProjectLineItem $record, array $data) => $record->update(['order_status' => OrderStatus::Ordered, 'expected_on' => $data['expected_on'] ?? $record->expected_on])),
                Action::make('markReceived')
                    ->label('Mark received')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProjectLineItem $record): bool => $record->order_status === OrderStatus::Ordered)
                    ->action(fn (ProjectLineItem $record) => $record->update(['order_status' => OrderStatus::Received])),
            ])
            ->toolbarActions([
                BulkAction::make('markOrdered')
                    ->label('Mark ordered')
                    ->icon(Heroicon::OutlinedTruck)
                    ->schema([DatePicker::make('expected_on')->label('Expected')->native(false)])
                    ->modalWidth('sm')
                    ->action(fn (Collection $records, array $data) => self::mark($records, OrderStatus::Ordered, $data['expected_on'] ?? null))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('markReceived')
                    ->label('Mark received')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Stocked parts go into stock now, and come out when their job is completed.')
                    ->action(fn (Collection $records) => self::mark($records, OrderStatus::Received))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Nothing to order')
            ->emptyStateDescription('On a job’s line items, set Order to “To order” for parts that have to be bought for it.');
    }

    /**
     * One line at a time, so each moves its own job along and stamps its own dates.
     *
     * @param  Collection<int|string, Model>  $records
     */
    private static function mark(Collection $records, OrderStatus $status, ?string $expectedOn = null): void
    {
        foreach ($records as $record) {
            if ($record instanceof ProjectLineItem) {
                $record->update(['order_status' => $status, 'expected_on' => $expectedOn ?? $record->expected_on]);
            }
        }
    }
}
