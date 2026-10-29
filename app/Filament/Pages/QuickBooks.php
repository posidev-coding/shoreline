<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\TokenManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The QuickBooks connection, for super admins: connect a company, check it, disconnect it, or
 * forget it to connect another. Connecting goes through Intuit's sign-in and comes back to
 * QuickBooksCallback.
 */
class QuickBooks extends Page
{
    public const string STATE_SESSION_KEY = 'quickbooks.oauth_state';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'QuickBooks';

    protected static ?string $title = 'QuickBooks';

    protected static ?string $slug = 'quickbooks';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::SuperAdmin;
    }

    public function content(Schema $schema): Schema
    {
        $connection = QuickBooksConnection::current();
        $timezone = config('app.business_timezone');
        $when = fn (?Carbon $moment): ?string => $moment?->timezone($timezone)->format('M j, Y g:i A');

        return $schema->components([
            Section::make('Connection')
                ->columns(2)
                ->schema([
                    TextEntry::make('status')
                        ->state(match (true) {
                            $connection === null => 'Not connected',
                            $connection->isConnected() => 'Connected',
                            default => 'Disconnected',
                        })
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Connected' ? 'success' : 'warning'),
                    TextEntry::make('company')
                        ->state($connection ? ($connection->company_name ?? 'Name not loaded yet') : null)
                        ->helperText($connection ? "{$connection->environment->getLabel()} company {$connection->realm_id}" : null)
                        ->placeholder('None'),
                    TextEntry::make('connected')
                        ->state($connection?->connected_at ? $when($connection->connected_at).' by '.($connection->connectedBy->name ?? 'a former admin') : null)
                        ->placeholder('Never')
                        ->visible($connection !== null),
                    TextEntry::make('disconnected')
                        ->state($connection?->disconnected_at ? $when($connection->disconnected_at) : null)
                        ->helperText($connection?->disconnect_reason)
                        ->visible($connection?->disconnected_at !== null),
                    TextEntry::make('access')
                        ->label('Access token')
                        ->state($connection?->isConnected() ? 'Refreshed '.$connection->last_refreshed_at?->diffForHumans().', good until '.$when($connection->access_expires_at) : null)
                        ->helperText('Lasts an hour, and refreshes itself when it’s needed.')
                        ->visible($connection?->isConnected() === true),
                    TextEntry::make('refresh')
                        ->label('Refresh token')
                        ->state($connection?->isConnected() ? 'Lapses if unused by '.($when($connection->refresh_expires_at) ?? 'an unreported date') : null)
                        ->helperText($connection?->refresh_hard_expires_at
                            ? 'Stops for good on '.$when($connection->refresh_hard_expires_at).'. You’ll be emailed from 60 days before.'
                            : 'QuickBooks hasn’t reported when it stops for good (at most five years). It’s renewed daily, so it doesn’t lapse.')
                        ->visible($connection?->isConnected() === true),
                    TextEntry::make('redirect')
                        ->label('Redirect URI')
                        ->state(QuickBooksCallback::redirectUri())
                        ->helperText('The Intuit app’s keys must list this exact address under Redirect URIs.')
                        ->copyable()
                        ->columnSpanFull(),
                    TextEntry::make('keys')
                        ->label('Intuit app keys')
                        ->state('QUICKBOOKS_CLIENT_ID and QUICKBOOKS_CLIENT_SECRET aren’t set, so nothing can connect.')
                        ->color('danger')
                        ->visible(! app(TokenManager::class)->isConfigured())
                        ->columnSpanFull(),
                ]),
            Section::make('Company settings in QuickBooks')
                ->description($connection?->preferences_checked_at ? 'As QuickBooks reported them '.$when($connection->preferences_checked_at).'. The sandbox test in M5 confirms what each one means for the app.' : null)
                ->columns(2)
                ->visible($connection?->preferences !== null)
                ->schema([
                    $this->preference($connection, 'Custom transaction numbers', 'SalesFormsPrefs.CustomTxnNumbers'),
                    $this->preference($connection, 'Automated sales tax', 'TaxPrefs.PartnerTaxEnabled'),
                    $this->preference($connection, 'Online card payments', 'SalesFormsPrefs.AllowOnlineCreditCardPayment'),
                    $this->preference($connection, 'Online bank payments', 'SalesFormsPrefs.AllowOnlineACHPayment'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $connection = QuickBooksConnection::current();
        $isConnected = $connection?->isConnected() === true;

        return [
            Action::make('connect')
                ->label($connection === null ? 'Connect to QuickBooks' : 'Connect again')
                ->icon(Heroicon::OutlinedLink)
                ->visible(! $isConnected && app(TokenManager::class)->isConfigured())
                ->action(function (TokenManager $tokens): void {
                    $state = Str::random(40);
                    session()->put(self::STATE_SESSION_KEY, $state);

                    $this->redirect($tokens->authorizeUrl($state, QuickBooksCallback::redirectUri()));
                }),
            Action::make('check')
                ->label('Check the connection')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible($isConnected)
                ->action(function (): void {
                    try {
                        $company = QuickBooksClient::for($this->connection())->refreshCompanyDetails()->company_name;
                        Notification::make()->success()->title("Connected to {$company}")->send();
                    } catch (QuickBooksException $exception) {
                        Notification::make()->danger()->title('QuickBooks didn’t answer as expected')->body($exception->getMessage())->persistent()->send();
                    }
                }),
            Action::make('disconnect')
                ->color('danger')
                ->visible($isConnected)
                ->requiresConfirmation()
                ->modalDescription('The app’s access is revoked in QuickBooks, and nothing syncs until it’s connected again.')
                ->action(function (TokenManager $tokens): void {
                    $tokens->disconnect($this->connection(), $this->user());
                    Notification::make()->success()->title('Disconnected from QuickBooks')->send();
                }),
            Action::make('forget')
                ->label('Forget this company')
                ->color('danger')
                ->visible($connection !== null && ! $isConnected)
                ->requiresConfirmation()
                ->modalDescription('So that a different QuickBooks company can be connected. Its customers and items leave the app: clients are unlinked from them, and parts and job lines lose the item they bill as. Nothing in QuickBooks changes. To use this same company again, Connect again instead, which keeps all of that.')
                ->action(function (): void {
                    $connection = $this->connection();
                    $realm = $connection->realm_id;

                    DB::transaction(function () use ($connection, $realm): void {
                        $clients = Client::withTrashed()->where('realm_id', $realm)->toBase()->update([
                            'realm_id' => null, 'qb_id' => null, 'qb_sync_token' => null, 'qb_last_updated_at' => null, 'qb_linked_at' => null,
                        ]);
                        $customers = QuickBooksCustomer::query()->where('realm_id', $realm)->delete();
                        // Parts and job lines let go of the items by foreign key.
                        $items = CatalogItem::query()->where('realm_id', $realm)->delete();

                        activity()->causedBy($this->user())->event('quickbooks')
                            ->log("Forgot QuickBooks company {$realm}: unlinked {$clients} ".str('client')->plural($clients)
                                .", removed {$customers} ".str('customer')->plural($customers)." and {$items} ".str('item')->plural($items));
                        $connection->delete();
                    });

                    Notification::make()->success()->title('Forgotten. Another company can be connected now.')->send();
                }),
        ];
    }

    private function preference(?QuickBooksConnection $connection, string $label, string $key): TextEntry
    {
        $value = data_get($connection?->preferences, $key);

        return TextEntry::make(Str::slug($label))
            ->label($label)
            ->state(match ($value) {
                true => 'On',
                false => 'Off',
                default => 'Not reported',
            })
            ->helperText($key);
    }

    /**
     * The connection an action works on, found again when it runs rather than trusted from when
     * the page was drawn.
     */
    private function connection(): QuickBooksConnection
    {
        $connection = QuickBooksConnection::current();
        abort_if($connection === null, 404);

        return $connection;
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
