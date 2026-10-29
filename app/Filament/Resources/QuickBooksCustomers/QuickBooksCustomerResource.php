<?php

namespace App\Filament\Resources\QuickBooksCustomers;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\QuickBooksCustomers\Pages\ManageQuickBooksCustomers;
use App\Filament\Support\ContactFields;
use App\Models\Client;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use App\Services\QuickBooks\CustomerLinker;
use App\Services\QuickBooks\CustomerMatcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use UnitEnum;

/**
 * QuickBooks' customers as last imported, and which client each one is: the owner reviews the
 * ones the automatic match left, linking each to a client, making a client from it, or skipping
 * it. Sub-customers and QuickBooks projects are listed but not linked: they belong to
 * properties, which the customer sync handles (M6).
 */
class QuickBooksCustomerResource extends Resource
{
    protected static ?string $model = QuickBooksCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'QuickBooks customers';

    protected static ?string $modelLabel = 'QuickBooks customer';

    protected static ?string $pluralModelLabel = 'QuickBooks customers';

    protected static ?string $slug = 'quickbooks-customers';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'parent']))
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Customer')
                    ->searchable(['display_name', 'company_name', 'given_name', 'family_name', 'email'])
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (QuickBooksCustomer $record): ?string => $record->company_name !== $record->display_name ? $record->company_name : null)
                    ->wrap(),
                TextColumn::make('email')
                    ->placeholder('None'),
                TextColumn::make('phone')
                    ->formatStateUsing(fn (string $state): ?string => ContactFields::national($state))
                    ->placeholder('None'),
                TextColumn::make('billing_city')
                    ->label('City')
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.display_name')
                    ->label('Client')
                    ->description(fn (QuickBooksCustomer $record): ?string => $record->client?->trashed() ? 'In the trash' : null)
                    ->url(fn (QuickBooksCustomer $record): ?string => $record->client ? ClientResource::getUrl('view', ['record' => $record->client]) : null)
                    ->placeholder('Not linked')
                    ->visible(fn (ManageQuickBooksCustomers $livewire): bool => in_array($livewire->activeTab, ['linked', 'all'], true)),
                TextColumn::make('possible_matches')
                    ->label('Possible matches')
                    ->state(fn (QuickBooksCustomer $record): ?string => app(CustomerMatcher::class)->suggestions($record)->pluck('display_name')->implode(', ') ?: null)
                    ->placeholder('None')
                    ->visible(fn (ManageQuickBooksCustomers $livewire): bool => $livewire->activeTab === 'review'),
                TextColumn::make('parent.display_name')
                    ->label('Under')
                    ->description(fn (QuickBooksCustomer $record): ?string => $record->is_project ? 'QuickBooks project' : null)
                    ->visible(fn (ManageQuickBooksCustomers $livewire): bool => $livewire->activeTab === 'sub'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                self::linkAction(),
                Action::make('createClient')
                    ->label('Create client')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (QuickBooksCustomer $record): bool => self::isLinkable($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (QuickBooksCustomer $record): string => "Create a client from {$record->display_name}")
                    ->modalDescription('With the customer’s name, company, email, phone, and billing address, linked to it. Add a property when there’s a job.')
                    ->modalSubmitActionLabel('Create client')
                    ->action(function (QuickBooksCustomer $record, CustomerLinker $linker): void {
                        try {
                            $client = $linker->createClient($record, self::user());
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title("Created {$client->display_name}")->send();
                    }),
                Action::make('skip')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->tooltip('Not a client, or not one to bring over. Skipped customers stay in QuickBooks.')
                    ->visible(fn (QuickBooksCustomer $record): bool => self::isLinkable($record) && $record->skipped_at === null)
                    ->action(fn (QuickBooksCustomer $record, CustomerLinker $linker) => $linker->skip($record)),
                Action::make('unskip')
                    ->label('Review again')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (QuickBooksCustomer $record): bool => self::isLinkable($record) && $record->skipped_at !== null)
                    ->action(fn (QuickBooksCustomer $record, CustomerLinker $linker) => $linker->unskip($record)),
                Action::make('unlink')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->color('danger')
                    ->visible(fn (QuickBooksCustomer $record): bool => $record->client !== null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (QuickBooksCustomer $record): string => "Unlink {$record->display_name}")
                    ->modalDescription(fn (QuickBooksCustomer $record): string => "{$record->client?->display_name} stops being this QuickBooks customer, and the customer goes back to review. Nothing changes in QuickBooks.")
                    ->action(function (QuickBooksCustomer $record, CustomerLinker $linker): void {
                        if ($record->client !== null) {
                            $linker->unlink($record->client, self::user());
                        }
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('createClients')
                    ->label('Create clients')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->requiresConfirmation()
                    ->modalDescription('A client for each selected customer that isn’t linked, with its name, company, email, phone, and billing address. A customer whose name a client already has is left for you to link.')
                    ->action(function (Collection $records, CustomerLinker $linker): void {
                        $created = 0;
                        $left = 0;

                        foreach ($records as $record) {
                            /** @var QuickBooksCustomer $record */
                            if (! self::isLinkable($record)) {
                                continue;
                            }

                            try {
                                $linker->createClient($record, self::user());
                                $created++;
                            } catch (InvalidArgumentException) {
                                $left++;
                            }
                        }

                        Notification::make()->success()
                            ->title("Created {$created} ".str('client')->plural($created))
                            ->body($left > 0 ? "{$left} left to link: a client already has that name." : null)
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('skip')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->action(function (Collection $records, CustomerLinker $linker): void {
                        foreach ($records as $record) {
                            /** @var QuickBooksCustomer $record */
                            if (self::isLinkable($record)) {
                                $linker->skip($record);
                            }
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('Nothing here')
            ->emptyStateDescription('Customers come in from QuickBooks when it’s connected, or with Import from QuickBooks.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuickBooksCustomers::route('/'),
        ];
    }

    /**
     * Top-level customers that aren't linked yet. Sub-customers wait for properties (M6).
     */
    public static function isLinkable(QuickBooksCustomer $record): bool
    {
        return ! $record->isSubCustomer() && $record->client === null;
    }

    private static function linkAction(): Action
    {
        return Action::make('link')
            ->icon(Heroicon::OutlinedLink)
            ->visible(fn (QuickBooksCustomer $record): bool => self::isLinkable($record))
            ->modalHeading(fn (QuickBooksCustomer $record): string => "Link {$record->display_name} to a client")
            ->modalDescription('Nothing changes on either side now. Once QuickBooks sync is on, QuickBooks’ name, company, billing address, and email win for this client.')
            ->modalSubmitActionLabel('Link')
            ->fillForm(function (QuickBooksCustomer $record): array {
                $suggestions = app(CustomerMatcher::class)->suggestions($record);

                return ['client_id' => $suggestions->count() === 1 ? $suggestions->sole()->getKey() : null];
            })
            ->schema([
                Select::make('client_id')
                    ->label('Client')
                    ->helperText('Clients with the same email, phone, or name are listed first. Search for any other. Clients already linked aren’t listed.')
                    ->searchable()
                    ->options(fn (QuickBooksCustomer $record): array => self::clientOptions(Client::query()->whereKey(app(CustomerMatcher::class)->suggestions($record)->modelKeys())))
                    ->getSearchResultsUsing(fn (string $search): array => self::clientOptions(Client::query()->where(fn (Builder $query) => $query
                        ->whereLike('display_name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('company', "%{$search}%"))))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::query()->whereKey($value)->value('display_name'))
                    ->required(),
            ])
            ->action(function (QuickBooksCustomer $record, array $data, CustomerLinker $linker): void {
                $client = Client::query()->whereKey($data['client_id'])->firstOrFail();

                try {
                    $linker->link($record, $client, self::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title("Linked to {$client->display_name}")->send();
            });
    }

    /**
     * Unlinked clients for the link picker, with their email to tell apart two of a name.
     *
     * @param  Builder<Client>  $clients
     * @return array<int, string>
     */
    private static function clientOptions(Builder $clients): array
    {
        return $clients
            ->whereNull('qb_id')
            ->orderBy('display_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Client $client): array => [$client->getKey() => $client->display_name.($client->email ? " · {$client->email}" : '')])
            ->all();
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
