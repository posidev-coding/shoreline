<?php

use App\Enums\CustomerSyncState;
use App\Enums\SyncIssueKind;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\QuickBooksSyncIssues\Pages\ManageQuickBooksSyncIssues;
use App\Jobs\PushQuickBooksCustomer;
use App\Jobs\SweepQuickBooksChanges;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;
use App\Services\QuickBooks\CustomerFields;
use App\Services\QuickBooks\CustomerImporter;
use App\Services\QuickBooks\CustomerLinker;
use App\Services\QuickBooks\CustomerSync;
use App\Services\QuickBooks\QuickBooksException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs($this->admin = User::factory()->superAdmin()->withMfa()->create());
    $this->connection = QuickBooksConnection::factory()->create(['realm_id' => '9130350000000001']);
});

/**
 * A QuickBooks customer as the API returns it, matching syncedClient()'s fields unless changed.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function qbSyncCustomer(array $changes = []): array
{
    return array_replace_recursive([
        'Id' => '41', 'SyncToken' => '3', 'DisplayName' => 'Pat Lee', 'GivenName' => 'Pat', 'FamilyName' => 'Lee',
        'PrimaryEmailAddr' => ['Address' => 'pat@example.com'], 'PrimaryPhone' => ['FreeFormNumber' => '(252) 555-0142'],
        'BillAddr' => ['Line1' => '210 Front St', 'City' => 'Beaufort', 'CountrySubDivisionCode' => 'NC', 'PostalCode' => '28516'],
        'MetaData' => ['LastUpdatedTime' => '2026-11-05T09:00:00-05:00'],
    ], $changes);
}

/**
 * A client linked to customer 41, agreed with it on qbSyncCustomer()'s fields.
 *
 * @param  array<string, mixed>  $changes  Changes made in the app since they last agreed.
 */
function syncedClient(array $changes = []): Client
{
    $client = Client::factory()->create([
        'display_name' => 'Pat Lee', 'first_name' => 'Pat', 'last_name' => 'Lee', 'company' => null, 'email' => 'pat@example.com',
        'phone' => '+12525550142', 'billing_street' => '210 Front St', 'billing_city' => 'Beaufort', 'billing_state' => 'NC', 'billing_zip' => '28516',
    ]);
    $client->forceFill([
        'realm_id' => '9130350000000001', 'qb_id' => '41', 'qb_sync_token' => '3',
        'qb_synced_fields' => CustomerFields::fromQuickBooks(qbSyncCustomer()), 'qb_sync_state' => CustomerSyncState::Clean,
    ])->save();

    if ($changes !== []) {
        // As if edited a minute after QuickBooks last changed.
        test()->travelTo('2026-11-05 14:01:00');
        CustomerSync::inbound(fn () => $client->fill($changes)->save());
        test()->travelBack();
    }

    return $client->refresh();
}

/**
 * QuickBooks answering a read of customer 41 with $remote, and a sparse update with the update
 * applied (SyncToken one higher). Records what was sent.
 *
 * @param  array<string, mixed>  $remote
 */
function fakeCustomerApi(array $remote, ?Closure $onUpdate = null): void
{
    Http::fake(['sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/customer*' => function (Request $request) use (&$remote, $onUpdate) {
        if ($request->method() === 'GET') {
            return Http::response(['Customer' => $remote]);
        }

        if ($onUpdate !== null && ($answer = $onUpdate($request)) !== null) {
            return $answer;
        }

        $remote = [...$remote, ...collect($request->data())->except(['sparse'])->all(), 'SyncToken' => (string) ((int) $remote['SyncToken'] + 1)];

        return Http::response(['Customer' => $remote]);
    }]);
}

/**
 * The sparse updates sent to QuickBooks.
 *
 * @return list<array<string, mixed>>
 */
function sentUpdates(): array
{
    return Http::recorded(fn (Request $request): bool => $request->method() === 'POST')->map(fn (array $pair): array => $pair[0]->data())->values()->all();
}

describe('fields', function () {
    it('reads both sides into the same shape', function () {
        $client = Client::factory()->make([
            'display_name' => 'Lee: Rentals', 'email' => 'Pat@Example.com', 'phone' => '+12525550142',
            'billing_street' => '210 Front St, Suite 2', 'billing_state' => 'nc', 'company' => ' ',
        ]);
        $customer = qbSyncCustomer([
            'DisplayName' => 'Lee- Rentals', 'PrimaryEmailAddr' => ['Address' => 'PAT@example.com, office@example.com'],
            'BillAddr' => ['Line2' => 'Suite 2', 'CountrySubDivisionCode' => 'North Carolina'],
        ]);

        expect(CustomerFields::fromClient($client))
            ->display_name->toBe('Lee- Rentals')
            ->email->toBe('pat@example.com')
            ->billing_state->toBe('NC')
            ->company->toBeNull()
            ->and(CustomerFields::fromQuickBooks($customer))
            ->display_name->toBe('Lee- Rentals')
            ->email->toBe('pat@example.com')
            ->phone->toBe('+12525550142')
            ->billing_street->toBe('210 Front St, Suite 2')
            ->billing_state->toBeNull();
    });
});

describe('sending changes', function () {
    it('queues a push when a linked client’s QuickBooks fields change, and only then', function () {
        Queue::fake();
        $client = syncedClient();

        $client->update(['notes' => 'Gate code is in the lockbox']);
        Queue::assertNothingPushed();

        $client->update(['phone' => '+12525550199']);
        expect($client->refresh()->qb_sync_state)->toBe(CustomerSyncState::Pending);
        Queue::assertPushed(PushQuickBooksCustomer::class, fn (PushQuickBooksCustomer $job): bool => $job->clientId === $client->getKey());

        Client::factory()->create()->update(['phone' => '+12525550177']);
        CustomerSync::inbound(fn () => $client->update(['email' => 'new@example.com']));
        Queue::assertPushed(PushQuickBooksCustomer::class, 1);
    });

    it('sends only what changed, with the latest SyncToken, and agrees on QuickBooks’ copy', function () {
        $client = syncedClient(['phone' => '+12525550199']);
        fakeCustomerApi(qbSyncCustomer());

        app(CustomerSync::class)->push($this->connection, $client);

        expect(sentUpdates())->toBe([['Id' => '41', 'SyncToken' => '3', 'sparse' => true, 'PrimaryPhone' => ['FreeFormNumber' => '(252) 555-0199']]])
            ->and($client->refresh())
            ->qb_sync_state->toBe(CustomerSyncState::Clean)
            ->qb_sync_token->toBe('4')
            ->and($client->qb_synced_fields['phone'])->toBe('+12525550199')
            ->and(QuickBooksCustomer::query()->where('qb_id', '41')->sole()->phone)->toBe('+12525550199');
    });

    it('takes QuickBooks’ changes to other fields on the way', function () {
        $client = syncedClient(['phone' => '+12525550199']);
        fakeCustomerApi(qbSyncCustomer(['PrimaryEmailAddr' => ['Address' => 'pat.lee@example.com']]));

        app(CustomerSync::class)->push($this->connection, $client);

        expect(array_keys(sentUpdates()[0]))->toBe(['Id', 'SyncToken', 'sparse', 'PrimaryPhone'])
            ->and($client->refresh())->email->toBe('pat.lee@example.com')->phone->toBe('+12525550199')
            ->and(QuickBooksSyncIssue::query()->exists())->toBeFalse();
    });

    it('keeps QuickBooks’ value when both sides changed a field, and says what was dropped', function () {
        $client = syncedClient(['email' => 'pat.app@example.com']);
        fakeCustomerApi(qbSyncCustomer(['PrimaryEmailAddr' => ['Address' => 'pat.qb@example.com']]));

        app(CustomerSync::class)->push($this->connection, $client);

        expect(sentUpdates())->toBe([])
            ->and($client->refresh()->email)->toBe('pat.qb@example.com')
            ->and(QuickBooksSyncIssue::query()->sole())
            ->kind->toBe(SyncIssueKind::CustomerConflict)
            ->message->toContain('email: kept QuickBooks’ “pat.qb@example.com”, not “pat.app@example.com”');
    });

    it('gives the phone to the newer edit when both sides changed it', function (string $theirsUpdatedAt, string $kept) {
        $client = syncedClient(['phone' => '+12525550199']);
        fakeCustomerApi(qbSyncCustomer(['PrimaryPhone' => ['FreeFormNumber' => '252-555-0177'], 'MetaData' => ['LastUpdatedTime' => $theirsUpdatedAt]]));

        app(CustomerSync::class)->push($this->connection, $client);

        expect($client->refresh()->phone)->toBe($kept)
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::CustomerConflict);
    })->with([
        'the app’s is newer' => ['2026-11-05T09:00:30-05:00', '+12525550199'],
        'QuickBooks’ is newer' => ['2026-11-05T09:05:00-05:00', '+12525550177'],
    ]);

    it('reads again and retries when the SyncToken went stale', function () {
        $client = syncedClient(['phone' => '+12525550199']);
        $refusals = 1;
        fakeCustomerApi(qbSyncCustomer(), function () use (&$refusals) {
            return $refusals-- > 0 ? Http::response(['Fault' => ['Error' => [['Message' => 'Stale Object Error', 'code' => '5010']]]], 400) : null;
        });

        app(CustomerSync::class)->push($this->connection, $client);

        expect(sentUpdates())->toHaveCount(2)
            ->and($client->refresh()->qb_sync_state)->toBe(CustomerSyncState::Clean);
    });

    it('keeps QuickBooks’ name when the new one is taken there', function () {
        $client = syncedClient(['display_name' => 'Patricia Lee', 'phone' => '+12525550199']);
        fakeCustomerApi(qbSyncCustomer(), fn (Request $request) => isset($request->data()['DisplayName'])
            ? Http::response(['Fault' => ['Error' => [['Message' => 'Duplicate Name Exists Error', 'code' => '6240']]]], 400)
            : null);

        app(CustomerSync::class)->push($this->connection, $client);

        expect($client->refresh())->display_name->toBe('Pat Lee')->phone->toBe('+12525550199')->qb_sync_state->toBe(CustomerSyncState::Clean)
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::NameTaken);
    });

    it('unlinks the client when its customer is gone from QuickBooks', function () {
        $client = syncedClient(['phone' => '+12525550199']);
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => Http::response(['Fault' => ['Error' => [['Message' => 'Object Not Found', 'code' => '610']]]], 400)]);

        app(CustomerSync::class)->push($this->connection, $client);

        expect($client->refresh()->qb_id)->toBeNull()
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::CustomerDeleted);
    });

    it('marks the client and raises an issue when it keeps failing, and Retry sends it again', function () {
        $client = syncedClient(['phone' => '+12525550199']);

        (new PushQuickBooksCustomer($client->getKey()))->failed(new QuickBooksException('QuickBooks answered 500: Down', 500, null, 'tid-5'));

        expect($client->refresh()->qb_sync_state)->toBe(CustomerSyncState::Error)
            ->and($issue = QuickBooksSyncIssue::query()->sole())
            ->kind->toBe(SyncIssueKind::PushFailed)
            ->intuit_tid->toBe('tid-5');

        fakeCustomerApi(qbSyncCustomer());
        Livewire::test(ManageQuickBooksSyncIssues::class)
            ->callAction(TestAction::make('retry')->table($issue))
            ->assertNotified('Synced Customer 41');

        expect($client->refresh()->qb_sync_state)->toBe(CustomerSyncState::Clean)
            ->and($issue->refresh()->isOpen())->toBeFalse();
    });
});

describe('taking QuickBooks’ changes', function () {
    it('brings a changed customer’s fields into its client, in its history', function () {
        Queue::fake();
        $client = syncedClient();

        app(CustomerImporter::class)->store('9130350000000001', qbSyncCustomer([
            'SyncToken' => '4', 'BillAddr' => ['Line1' => '12 Oak St', 'Line2' => 'Unit B'], 'MetaData' => ['LastUpdatedTime' => '2026-11-05T10:00:00-05:00'],
        ]));

        expect($client->refresh())
            ->billing_street->toBe('12 Oak St, Unit B')
            ->qb_sync_token->toBe('4')
            ->qb_sync_state->toBe(CustomerSyncState::Clean)
            ->and(Activity::query()->where('subject_id', $client->getKey())->where('event', 'updated')->sole()->attribute_changes['attributes'])->toBe(['billing_street' => '12 Oak St, Unit B']);
        Queue::assertNothingPushed();
    });

    it('keeps the client’s own unsent change, and queues it', function () {
        Queue::fake();
        $client = syncedClient(['phone' => '+12525550199']);

        app(CustomerImporter::class)->store('9130350000000001', qbSyncCustomer(['SyncToken' => '4', 'CompanyName' => 'Lee Rentals']));

        expect($client->refresh())->company->toBe('Lee Rentals')->phone->toBe('+12525550199')->qb_sync_state->toBe(CustomerSyncState::Pending);
        Queue::assertPushed(PushQuickBooksCustomer::class);
    });

    it('lets QuickBooks win a first sync, and fills its blanks from the client', function () {
        Queue::fake();
        $client = Client::factory()->create(['display_name' => 'Patricia Lee', 'email' => 'pat@old.example.com', 'phone' => '+12525550199']);
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '41'])->save();

        app(CustomerImporter::class)->store('9130350000000001', qbSyncCustomer(['PrimaryPhone' => ['FreeFormNumber' => '']]));

        expect($client->refresh())
            ->display_name->toBe('Pat Lee')
            ->email->toBe('pat@example.com')
            ->phone->toBe('+12525550199')
            ->qb_sync_state->toBe(CustomerSyncState::Pending)
            ->and(QuickBooksSyncIssue::query()->exists())->toBeFalse();
        Queue::assertPushed(PushQuickBooksCustomer::class);
    });

    it('keeps the client’s name when QuickBooks’ new one belongs to another client', function () {
        Queue::fake();
        Client::factory()->create(['display_name' => 'Lee Rentals']);
        $client = syncedClient();

        app(CustomerImporter::class)->store('9130350000000001', qbSyncCustomer(['DisplayName' => 'Lee Rentals', 'MetaData' => ['LastUpdatedTime' => '2026-11-05T10:00:00-05:00']]));

        expect($client->refresh()->display_name)->toBe('Pat Lee')
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::NameTaken);
    });
});

describe('adding a client to QuickBooks', function () {
    it('makes a new customer, with a suffix when QuickBooks has the name', function () {
        $client = Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'pat@example.com', 'phone' => '+12525550142', 'billing_street' => '210 Front St', 'billing_city' => 'Beaufort', 'billing_state' => 'NC', 'billing_zip' => '28516']);
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => function (Request $request) {
            return $request->data()['DisplayName'] === 'Pat Lee'
                ? Http::response(['Fault' => ['Error' => [['Message' => 'Duplicate Name Exists Error', 'code' => '6240']]]], 400)
                : Http::response(['Customer' => [...$request->data(), 'Id' => '77', 'SyncToken' => '0', 'MetaData' => ['LastUpdatedTime' => '2026-11-05T10:00:00-05:00']]]);
        }]);

        $customer = app(CustomerSync::class)->create($this->connection, $client, $this->admin);

        expect(sentUpdates()[1])->toMatchArray([
            'DisplayName' => 'Pat Lee (2)', 'PrimaryEmailAddr' => ['Address' => 'pat@example.com'], 'PrimaryPhone' => ['FreeFormNumber' => '(252) 555-0142'],
            'BillAddr' => ['Line1' => '210 Front St', 'City' => 'Beaufort', 'CountrySubDivisionCode' => 'NC', 'PostalCode' => '28516'],
        ])
            ->and($customer->qb_id)->toBe('77')
            ->and($client->refresh())->qb_id->toBe('77')->display_name->toBe('Pat Lee (2)')->qb_sync_state->toBe(CustomerSyncState::Clean)
            ->and(Activity::query()->where('event', 'quickbooks')->sole()->description)->toBe('Added to QuickBooks as customer Pat Lee (2) (77)')
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::NameTaken);
    });

    it('offers matching QuickBooks customers first on the client’s page', function () {
        Queue::fake();
        $client = Client::factory()->create(['email' => 'pat@example.com']);
        $match = QuickBooksCustomer::factory()->create(['qb_id' => '41', 'display_name' => 'Pat Lee (QB)', 'email' => 'pat@example.com']);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->mountAction(TestAction::make('addToQuickBooks'))
            ->assertSchemaStateSet(['customer' => null])
            ->fillForm(['customer' => (string) $match->getKey()])
            ->callMountedAction()
            ->assertNotified('In QuickBooks as Pat Lee (QB)');

        expect($client->refresh())->qb_id->toBe('41')->qb_sync_state->toBe(CustomerSyncState::Pending);
        Queue::assertPushed(PushQuickBooksCustomer::class);
    });

    it('is offered only for an unlinked client while QuickBooks is connected', function () {
        $client = syncedClient();

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])->assertActionHidden(TestAction::make('addToQuickBooks'));

        $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);
        Livewire::test(ViewClient::class, ['record' => Client::factory()->create()->getKey()])->assertActionHidden(TestAction::make('addToQuickBooks'));
    });
});

describe('catching up', function () {
    it('queues pushes the queue lost, and first syncs, from the hourly sweep', function () {
        Queue::fake();
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => Http::response(['time' => '2026-11-05T10:00:00-05:00', 'CDCResponse' => [['QueryResponse' => []]]])]);
        $lost = syncedClient();
        $lost->forceFill(['qb_sync_state' => CustomerSyncState::Pending, 'updated_at' => now()->subMinutes(30)])->saveQuietly();
        $recent = Client::factory()->create();
        $recent->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '42', 'qb_synced_fields' => [], 'qb_sync_state' => CustomerSyncState::Pending])->save();
        $neverSynced = Client::factory()->create();
        $neverSynced->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '43'])->save();

        app()->call([new SweepQuickBooksChanges, 'handle']);

        Queue::assertPushed(PushQuickBooksCustomer::class, 2);
        Queue::assertPushed(PushQuickBooksCustomer::class, fn (PushQuickBooksCustomer $job): bool => $job->clientId === $lost->getKey());
        Queue::assertPushed(PushQuickBooksCustomer::class, fn (PushQuickBooksCustomer $job): bool => $job->clientId === $neverSynced->getKey());
    });

    it('starts each new link with a first sync', function () {
        Queue::fake();
        $client = Client::factory()->create();

        app(CustomerLinker::class)->link(QuickBooksCustomer::factory()->create(['qb_id' => '41']), $client, $this->admin);

        expect($client->refresh())->qb_sync_state->toBe(CustomerSyncState::Pending)->qb_synced_fields->toBeNull();
        Queue::assertPushed(PushQuickBooksCustomer::class);
    });
});
