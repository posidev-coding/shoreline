<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\ContactFields;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use App\Services\QuickBooks\CustomerLinker;
use App\Services\QuickBooks\CustomerMatcher;
use App\Services\QuickBooks\CustomerSync;
use App\Services\QuickBooks\QuickBooksException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addToQuickBooksAction(),
            EditAction::make(),
            ActionGroup::make([
                ClientHistory::action(),
                DeleteAction::make()
                    ->modalDescription('Their properties and projects go to the trash with them. Restore the client to bring them all back.'),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription('This deletes the client, their properties, their projects, and the project photos for good. Use it when someone asks for their details to be erased.'),
            ]),
        ];
    }

    /**
     * Links the client to a QuickBooks customer it matches, or makes it a new one. Clients aren't
     * added to QuickBooks on their own, so one who's already there under an old record isn't
     * added twice.
     */
    private function addToQuickBooksAction(): Action
    {
        return Action::make('addToQuickBooks')
            ->label('Add to QuickBooks')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (Client $record): bool => $record->qb_id === null && QuickBooksConnection::current()?->isConnected() === true)
            ->modalDescription('Its name, company, email, phone, and billing address go to QuickBooks, and changes on either side sync from then on.')
            ->modalSubmitActionLabel('Add')
            ->schema(fn (Client $record): array => [
                Radio::make('customer')
                    ->label('In QuickBooks')
                    ->options(self::customerOptions($record))
                    ->default(app(CustomerMatcher::class)->customersFor($record)->isEmpty() ? 'new' : null)
                    ->helperText('Customers with the same email, phone, or name are listed. If one is this client, link it instead of adding them twice.')
                    ->required(),
            ])
            ->action(function (Client $record, array $data, CustomerSync $sync, CustomerLinker $linker): void {
                /** @var User $user */
                $user = auth()->user();
                $connection = QuickBooksConnection::current();
                abort_if($connection === null, 404);

                try {
                    if ($data['customer'] === 'new') {
                        $customer = $sync->create($connection, $record, $user);
                    } else {
                        $customer = QuickBooksCustomer::query()->whereKey($data['customer'])->firstOrFail();
                        $linker->link($customer, $record, $user);
                    }
                } catch (QuickBooksException|InvalidArgumentException $exception) {
                    Notification::make()->danger()->title('Not added to QuickBooks')->body($exception->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title("In QuickBooks as {$customer->display_name}")->send();
            });
    }

    /**
     * The matching customers, then "new", joined with + so the record ids stay the keys.
     *
     * @return array<int|string, string>
     */
    private static function customerOptions(Client $client): array
    {
        $matches = app(CustomerMatcher::class)->customersFor($client)
            ->mapWithKeys(fn (QuickBooksCustomer $customer): array => [$customer->getKey() => collect([
                "Link to {$customer->display_name}", $customer->email, ContactFields::national($customer->phone),
            ])->filter()->implode(' · ')])
            ->all();

        return $matches + ['new' => 'A new customer in QuickBooks'];
    }
}
