<?php

namespace App\Filament\Resources\QuickBooksSyncIssues;

use App\Enums\SyncIssueKind;
use App\Filament\Resources\QuickBooksSyncIssues\Pages\ManageQuickBooksSyncIssues;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What went wrong syncing with QuickBooks, for super admins: Retry pulls a record again, and
 * Resolve clears an issue someone has dealt with. A record that syncs later resolves its own
 * issue.
 */
class QuickBooksSyncIssueResource extends Resource
{
    protected static ?string $model = QuickBooksSyncIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Sync issues';

    protected static ?string $modelLabel = 'sync issue';

    protected static ?string $pluralModelLabel = 'sync issues';

    protected static ?string $slug = 'quickbooks-sync-issues';

    public static function getNavigationBadge(): ?string
    {
        $count = QuickBooksSyncIssue::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('kind')
                    ->badge()
                    ->color('danger')
                    ->description(fn (QuickBooksSyncIssue $record): ?string => $record->entity ? "{$record->entity} {$record->qb_id}" : null),
                TextColumn::make('message')
                    ->wrap()
                    ->description(fn (QuickBooksSyncIssue $record): ?string => $record->intuit_tid ? "intuit_tid {$record->intuit_tid}" : null),
                TextColumn::make('occurrences')
                    ->label('Times')
                    ->numeric(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->since()
                    ->dateTimeTooltip()
                    ->description(fn (QuickBooksSyncIssue $record): ?string => $record->resolved_at ? ($record->resolvedBy->name ?? 'By syncing') : null)
                    ->visible(fn (ManageQuickBooksSyncIssues $livewire): bool => $livewire->activeTab === 'resolved'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (QuickBooksSyncIssue $record): bool => $record->isOpen() && $record->kind->isRetryable()
                        && $record->entity !== null && $record->qb_id !== null
                        && QuickBooksConnection::current()?->isConnected() === true)
                    ->action(function (QuickBooksSyncIssue $record, EntitySync $sync): void {
                        $connection = QuickBooksConnection::current();
                        abort_if($connection === null || $record->entity === null || $record->qb_id === null, 404);

                        try {
                            $sync->pull($connection, $record->entity, $record->qb_id);
                        } catch (QuickBooksException $exception) {
                            Notification::make()->danger()->title('Still didn’t sync')->body($exception->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title("Synced {$record->entity} {$record->qb_id}")->send();
                    }),
                Action::make('resolve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (QuickBooksSyncIssue $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription(fn (QuickBooksSyncIssue $record): string => $record->kind === SyncIssueKind::CustomerDeleted
                        ? 'Once the client is linked to the right QuickBooks customer.'
                        : 'It comes back if the problem happens again.')
                    ->action(function (QuickBooksSyncIssue $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        SyncIssues::resolve($record, $user);
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('No sync issues')
            ->emptyStateDescription('Anything that fails to sync with QuickBooks shows here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuickBooksSyncIssues::route('/'),
        ];
    }
}
