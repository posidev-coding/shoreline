<?php

namespace App\Filament\Resources\QuickBooksCustomers\Pages;

use App\Filament\Resources\QuickBooksCustomers\QuickBooksCustomerResource;
use App\Jobs\ImportQuickBooksCustomers;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use App\Services\QuickBooks\CustomerMatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ManageQuickBooksCustomers extends ManageRecords
{
    protected static string $resource = QuickBooksCustomerResource::class;

    protected static ?string $title = 'QuickBooks customers';

    public function getSubheading(): string|Htmlable|null
    {
        if (! QuickBooksCustomer::query()->exists()) {
            return null;
        }

        $linked = QuickBooksCustomer::query()->linked()->count();
        $unlinkedClients = Client::query()->whereNull('qb_id')->count();

        return "{$linked} linked to clients. ".match ($unlinkedClients) {
            0 => 'Every client is linked.',
            1 => '1 client isn’t linked to a QuickBooks customer.',
            default => "{$unlinkedClients} clients aren’t linked to a QuickBooks customer.",
        };
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'review' => Tab::make('To review')
                ->modifyQueryUsing(fn (Builder $query) => $query->scopes('needsReview'))
                ->badge(QuickBooksCustomer::query()->needsReview()->count() ?: null),
            'linked' => Tab::make('Linked')
                ->modifyQueryUsing(fn (Builder $query) => $query->scopes('linked')),
            'skipped' => Tab::make('Skipped')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('skipped_at')),
            'sub' => Tab::make('Sub-customers and projects')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('parent_qb_id')),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'review';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('match')
                ->label('Match automatically')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (): bool => QuickBooksConnection::current() !== null && QuickBooksCustomer::query()->needsReview()->exists())
                ->action(function (CustomerMatcher $matcher): void {
                    $connection = QuickBooksConnection::current();
                    abort_if($connection === null, 404);

                    $counts = $matcher->matchAll($connection->realm_id);

                    Notification::make()->success()
                        ->title("{$counts['linked']} matched automatically")
                        ->body("{$counts['review']} left to review.")
                        ->send();
                }),
            Action::make('import')
                ->label('Import from QuickBooks')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => QuickBooksConnection::current()?->isConnected() === true)
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    ImportQuickBooksCustomers::dispatch($user);

                    Notification::make()->success()->title('Importing customers from QuickBooks')->body('The bell says when they’re in, and how many matched. Refresh the page to see them.')->send();
                }),
        ];
    }
}
