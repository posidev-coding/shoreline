<?php

use App\Enums\SyncIssueKind;
use App\Filament\Pages\QuickBooks;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\QuickBooksCustomers\Pages\ManageQuickBooksCustomers;
use App\Jobs\ImportQuickBooksCustomers;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;
use App\Services\QuickBooks\CustomerImporter;
use App\Services\QuickBooks\CustomerMatcher;
use App\Services\QuickBooks\SyncIssues;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
    $this->connection = QuickBooksConnection::factory()->create(['realm_id' => '9130350000000001']);
});

/**
 * Fakes the two customer queries: active customers, then inactive ones.
 *
 * @param  list<array<string, mixed>>  $active
 * @param  list<array<string, mixed>>  $inactive
 */
function fakeCustomers(array $active, array $inactive = []): void
{
    Http::fake(['sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/query*' => function (Request $request) use ($active, $inactive) {
        $rows = str_contains(urldecode($request->url()), 'Active = false') ? $inactive : $active;

        return Http::response(['QueryResponse' => $rows === [] ? [] : ['Customer' => $rows]]);
    }]);
}

function qbCustomer(string $id, string $name, array $extra = []): array
{
    return [
        'Id' => $id, 'DisplayName' => $name, 'Active' => true, 'Job' => false,
        'SyncToken' => '0', 'MetaData' => ['LastUpdatedTime' => '2026-10-20T09:15:00-07:00'], ...$extra,
    ];
}

describe('import', function () {
    it('stores each customer’s contact details the way clients store them', function () {
        fakeCustomers([
            qbCustomer('1', 'Pat Lee', [
                'GivenName' => 'Pat', 'FamilyName' => 'Lee', 'CompanyName' => 'Lee Rentals',
                'PrimaryEmailAddr' => ['Address' => 'Pat@Example.com, office@example.com'],
                'PrimaryPhone' => ['FreeFormNumber' => '(252) 555-0142'],
                'BillAddr' => ['Line1' => '210 Front St', 'City' => 'Beaufort', 'CountrySubDivisionCode' => 'NC', 'PostalCode' => '28516'],
            ]),
            qbCustomer('2', 'Sam Roe', ['PrimaryPhone' => ['FreeFormNumber' => 'ask at desk'], 'Mobile' => ['FreeFormNumber' => '252-555-0199']]),
            qbCustomer('3', 'Pat Lee:12 Oak St', ['Job' => true, 'ParentRef' => ['value' => '1']]),
            qbCustomer('4', 'Pat Lee:Kitchen', ['Job' => true, 'ParentRef' => ['value' => '1'], 'IsProject' => true]),
            qbCustomer('5', 'Odd Phone', ['PrimaryPhone' => ['FreeFormNumber' => '555']]),
        ], [qbCustomer('6', 'Gone Away', ['Active' => false])]);

        $importer = app(CustomerImporter::class);
        expect($importer->importPage($this->connection, false, 1, 500))->toBe(5)
            ->and($importer->importPage($this->connection, true, 1, 500))->toBe(1);

        expect(QuickBooksCustomer::query()->where('qb_id', '1')->sole())
            ->email->toBe('pat@example.com')
            ->phone->toBe('+12525550142')
            ->company_name->toBe('Lee Rentals')
            ->billing_city->toBe('Beaufort')
            ->parent_qb_id->toBeNull()
            ->qb_last_updated_at->toIso8601String()->toBe('2026-10-20T16:15:00+00:00')
            ->and(QuickBooksCustomer::query()->where('qb_id', '2')->sole()->phone)->toBe('+12525550199')
            ->and(QuickBooksCustomer::query()->where('qb_id', '3')->sole())->parent_qb_id->toBe('1')->is_project->toBeFalse()
            ->and(QuickBooksCustomer::query()->where('qb_id', '4')->sole())->parent_qb_id->toBe('1')->is_project->toBeTrue()
            ->and(QuickBooksCustomer::query()->where('qb_id', '5')->sole()->phone)->toBeNull()
            ->and(QuickBooksCustomer::query()->where('qb_id', '6')->sole()->is_active)->toBeFalse();
    });

    it('updates on a second import, and gives a linked client the new SyncToken', function () {
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => Http::sequence()
            ->push(['QueryResponse' => ['Customer' => [qbCustomer('1', 'Pat Lee')]]])
            ->push(['QueryResponse' => ['Customer' => [qbCustomer('1', 'Pat Lee-Roe', ['SyncToken' => '3'])]]])]);
        $importer = app(CustomerImporter::class);

        $importer->importPage($this->connection, false, 1, 500);
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '1', 'qb_sync_token' => '0'])->save();
        $importer->importPage($this->connection, false, 1, 500);

        expect(QuickBooksCustomer::query()->sole())->display_name->toBe('Pat Lee-Roe')->qb_sync_token->toBe('3')
            ->and($client->refresh())->qb_sync_token->toBe('3')->display_name->not->toBe('Pat Lee-Roe');
    });

    it('asks for the next page in another job while a page comes back full', function () {
        Queue::fake();
        fakeCustomers(array_map(fn (int $id): array => qbCustomer((string) $id, "Customer {$id}"), range(1, ImportQuickBooksCustomers::PAGE_SIZE)));

        app()->call([new ImportQuickBooksCustomers($this->owner), 'handle']);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'startposition 1 maxresults 500'));
        Queue::assertPushed(ImportQuickBooksCustomers::class, fn (ImportQuickBooksCustomers $job): bool => ! $job->inactive && $job->start === 501 && $job->imported === 500);
    });

    it('moves on to the inactive customers after a short page', function () {
        Queue::fake();
        fakeCustomers([qbCustomer('501', 'Customer 501')]);

        app()->call([new ImportQuickBooksCustomers($this->owner, start: 501, imported: 500), 'handle']);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'startposition 501 maxresults 500'));
        Queue::assertPushed(ImportQuickBooksCustomers::class, fn (ImportQuickBooksCustomers $job): bool => $job->inactive && $job->start === 1 && $job->imported === 501);
    });

    it('matches what it can after the last page, and says in the bell what’s left', function () {
        Client::factory()->create(['email' => 'pat@example.com']);
        fakeCustomers([
            qbCustomer('1', 'Pat Lee', ['PrimaryEmailAddr' => ['Address' => 'pat@example.com']]),
            qbCustomer('2', 'Sam Roe'),
        ], [qbCustomer('3', 'Gone Away', ['Active' => false])]);

        ImportQuickBooksCustomers::dispatchSync($this->owner);

        expect(QuickBooksCustomer::query()->count())->toBe(3)
            ->and($this->owner->notifications()->sole()->data)
            ->title->toBe('QuickBooks customers imported')
            ->body->toBe('3 imported. 1 matched to clients automatically, 2 left to review.');
    });

    it('imports from the customers page, only once QuickBooks is connected', function () {
        Queue::fake();

        Livewire::test(ManageQuickBooksCustomers::class)->callAction(TestAction::make('import'));
        Queue::assertPushed(ImportQuickBooksCustomers::class, fn (ImportQuickBooksCustomers $job): bool => $job->requestedBy?->is($this->owner) === true);

        $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);
        Livewire::test(ManageQuickBooksCustomers::class)->assertActionHidden(TestAction::make('import'));
    });
});

describe('automatic matching', function () {
    it('links one sure client by email, phone, or name, and logs it on the client', function () {
        $byEmail = Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'pat@example.com', 'phone' => null]);
        $byPhone = Client::factory()->create(['display_name' => 'Samuel Roe', 'email' => null, 'phone' => '+12525550199']);
        $byName = Client::factory()->create(['display_name' => 'Harbor Rentals', 'email' => 'office@harbor.example', 'phone' => null]);
        QuickBooksCustomer::factory()->create(['qb_id' => '1', 'display_name' => 'Patricia Lee', 'email' => 'pat@example.com', 'phone' => '+12525550100']);
        QuickBooksCustomer::factory()->create(['qb_id' => '2', 'display_name' => 'Sam Roe', 'email' => 'sam@example.com', 'phone' => '+12525550199']);
        QuickBooksCustomer::factory()->create(['qb_id' => '3', 'display_name' => 'harbor rentals', 'email' => null, 'phone' => '+12525550123']);

        expect(app(CustomerMatcher::class)->matchAll('9130350000000001'))->toBe(['linked' => 3, 'review' => 0])
            ->and($byEmail->refresh())->qb_id->toBe('1')->realm_id->toBe('9130350000000001')->qb_linked_at->not->toBeNull()
            ->and($byPhone->refresh()->qb_id)->toBe('2')
            ->and($byName->refresh()->qb_id)->toBe('3')
            ->and(Activity::query()->where('event', 'quickbooks')->where('subject_id', $byEmail->getKey())->sole())
            ->description->toBe('Matched automatically to QuickBooks customer Patricia Lee (1)');
    });

    it('leaves for review whatever isn’t sure', function () {
        // Same name, different email: probably two people.
        Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'pat@example.com', 'phone' => null]);
        QuickBooksCustomer::factory()->create(['qb_id' => '1', 'display_name' => 'Pat Lee', 'email' => 'p.lee@example.com', 'phone' => null]);
        // Same phone, but the emails differ.
        Client::factory()->create(['display_name' => 'Sam Roe', 'email' => 'sam@example.com', 'phone' => '+12525550199']);
        QuickBooksCustomer::factory()->create(['qb_id' => '2', 'display_name' => 'Samantha Roe', 'email' => 'samantha@example.com', 'phone' => '+12525550199']);
        // Two clients fit.
        Client::factory()->create(['display_name' => 'Jo Fox', 'email' => 'fox@example.com', 'phone' => null]);
        Client::factory()->create(['display_name' => 'Fox Rentals', 'email' => null, 'phone' => '+12525550177']);
        QuickBooksCustomer::factory()->create(['qb_id' => '3', 'display_name' => 'Jo Fox', 'email' => 'fox@example.com', 'phone' => '+12525550177']);
        // Two customers want one client.
        Client::factory()->create(['display_name' => 'The Kims', 'email' => 'kims@example.com', 'phone' => null]);
        QuickBooksCustomer::factory()->create(['qb_id' => '4', 'display_name' => 'Ann Kim', 'email' => 'kims@example.com', 'phone' => null]);
        QuickBooksCustomer::factory()->create(['qb_id' => '5', 'display_name' => 'Ben Kim', 'email' => 'kims@example.com', 'phone' => null]);

        expect(app(CustomerMatcher::class)->matchAll('9130350000000001'))->toBe(['linked' => 0, 'review' => 5])
            ->and(Client::query()->whereNotNull('qb_id')->exists())->toBeFalse();
    });

    it('passes over sub-customers, skipped customers, and clients already linked or in the trash', function () {
        Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'pat@example.com']);
        QuickBooksCustomer::factory()->create(['qb_id' => '3', 'display_name' => 'Pat Lee:12 Oak St', 'email' => 'pat@example.com', 'parent_qb_id' => '1']);
        $skipped = QuickBooksCustomer::factory()->create(['qb_id' => '4', 'email' => 'pat@example.com']);
        $skipped->forceFill(['skipped_at' => now()])->save();

        $linked = Client::factory()->create(['email' => 'sam@example.com']);
        $linked->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '50'])->save();
        Client::factory()->create(['email' => 'jo@example.com'])->delete();
        QuickBooksCustomer::factory()->create(['qb_id' => '5', 'email' => 'sam@example.com']);
        QuickBooksCustomer::factory()->create(['qb_id' => '6', 'email' => 'jo@example.com']);

        expect(app(CustomerMatcher::class)->matchAll('9130350000000001'))->toBe(['linked' => 0, 'review' => 2])
            ->and($linked->refresh()->qb_id)->toBe('50');
    });

    it('runs from the customers page', function () {
        $client = Client::factory()->create(['email' => 'pat@example.com', 'phone' => null]);
        QuickBooksCustomer::factory()->create(['qb_id' => '1', 'email' => 'pat@example.com']);

        Livewire::test(ManageQuickBooksCustomers::class)
            ->callAction(TestAction::make('match'))
            ->assertNotified('1 matched automatically');

        expect($client->refresh()->qb_id)->toBe('1');
    });
});

describe('review', function () {
    it('lists what needs review, with possible matches, and sub-customers on their own tab', function () {
        $suggested = Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'other@example.com']);
        $review = QuickBooksCustomer::factory()->create(['qb_id' => '1', 'display_name' => 'Pat Lee', 'email' => 'pat@example.com']);
        $sub = QuickBooksCustomer::factory()->create(['qb_id' => '2', 'display_name' => 'Pat Lee:12 Oak St', 'parent_qb_id' => '1']);
        $linked = QuickBooksCustomer::factory()->create(['qb_id' => '3']);
        Client::factory()->create()->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '3'])->save();

        Livewire::test(ManageQuickBooksCustomers::class)
            ->assertCanSeeTableRecords([$review])
            ->assertCanNotSeeTableRecords([$sub, $linked])
            ->assertTableColumnStateSet('possible_matches', $suggested->display_name, $review)
            ->set('activeTab', 'sub')
            ->assertCanSeeTableRecords([$sub])
            ->assertActionHidden(TestAction::make('link')->table($sub))
            ->set('activeTab', 'linked')
            ->assertCanSeeTableRecords([$linked]);
    });

    it('links a customer to the client picked, offering the one possible match first', function () {
        $client = Client::factory()->create(['display_name' => 'Pat Lee', 'email' => 'other@example.com']);
        $customer = QuickBooksCustomer::factory()->create(['qb_id' => '1', 'display_name' => 'Pat Lee', 'email' => 'pat@example.com']);

        Livewire::test(ManageQuickBooksCustomers::class)
            ->mountAction(TestAction::make('link')->table($customer))
            ->assertSchemaStateSet(['client_id' => $client->getKey()])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Linked to Pat Lee');

        expect($client->refresh())->qb_id->toBe('1')->email->toBe('other@example.com')
            ->and(Activity::query()->where('event', 'quickbooks')->sole())
            ->description->toBe('Linked to QuickBooks customer Pat Lee (1)')
            ->causer_id->toBe($this->owner->getKey());
    });

    it('won’t link a client that’s already linked', function () {
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '50'])->save();
        $customer = QuickBooksCustomer::factory()->create(['qb_id' => '1']);

        Livewire::test(ManageQuickBooksCustomers::class)
            ->callAction(TestAction::make('link')->table($customer), data: ['client_id' => $client->getKey()])
            ->assertNotified("{$client->display_name} is already linked to a QuickBooks customer.");

        expect($client->refresh()->qb_id)->toBe('50');
    });

    it('makes a client from a customer, with its contact details and billing address', function () {
        $customer = QuickBooksCustomer::factory()->create([
            'qb_id' => '1', 'display_name' => 'Lee Rentals', 'given_name' => 'Pat', 'family_name' => 'Lee', 'company_name' => 'Lee Rentals LLC',
            'email' => 'pat@example.com', 'phone' => '+12525550142',
            'qb_payload' => ['BillAddr' => ['Line1' => '210 Front St', 'Line2' => 'Suite 2', 'City' => 'Beaufort', 'CountrySubDivisionCode' => 'nc', 'PostalCode' => '28516']],
        ]);

        Livewire::test(ManageQuickBooksCustomers::class)
            ->callAction(TestAction::make('createClient')->table($customer))
            ->assertNotified('Created Lee Rentals');

        expect(Client::query()->sole())
            ->display_name->toBe('Lee Rentals')
            ->first_name->toBe('Pat')
            ->last_name->toBe('Lee')
            ->company->toBe('Lee Rentals LLC')
            ->email->toBe('pat@example.com')
            ->phone->toBe('+12525550142')
            ->billing_street->toBe('210 Front St, Suite 2')
            ->billing_city->toBe('Beaufort')
            ->billing_state->toBe('NC')
            ->billing_zip->toBe('28516')
            ->qb_id->toBe('1');
    });

    it('won’t make a client whose name another client has, even one in the trash', function () {
        Client::factory()->create(['display_name' => 'Pat Lee'])->delete();
        $customer = QuickBooksCustomer::factory()->create(['display_name' => 'pat lee']);

        Livewire::test(ManageQuickBooksCustomers::class)
            ->callAction(TestAction::make('createClient')->table($customer))
            ->assertNotified('A client named pat lee already exists. Link the customer to it instead.');

        expect(Client::withTrashed()->count())->toBe(1);
    });

    it('makes clients in bulk, leaving the ones whose names are taken', function () {
        Client::factory()->create(['display_name' => 'Pat Lee']);
        $taken = QuickBooksCustomer::factory()->create(['display_name' => 'Pat Lee']);
        $new = QuickBooksCustomer::factory()->count(2)->create();

        Livewire::test(ManageQuickBooksCustomers::class)
            ->selectTableRecords([$taken->getKey(), ...$new->modelKeys()])
            ->callAction(TestAction::make('createClients')->table()->bulk())
            ->assertNotified('Created 2 clients');

        expect(Client::query()->whereIn('qb_id', $new->pluck('qb_id'))->count())->toBe(2)
            ->and(QuickBooksCustomer::query()->needsReview()->sole()->is($taken))->toBeTrue();
    });

    it('skips a customer, and brings it back to review', function () {
        $customer = QuickBooksCustomer::factory()->create();
        $page = Livewire::test(ManageQuickBooksCustomers::class);

        $page->callAction(TestAction::make('skip')->table($customer));
        expect($customer->refresh()->skipped_at)->not->toBeNull()
            ->and(QuickBooksCustomer::query()->needsReview()->exists())->toBeFalse();

        $page->set('activeTab', 'skipped')->callAction(TestAction::make('unskip')->table($customer));
        expect($customer->refresh()->skipped_at)->toBeNull();
    });

    it('unlinks a client, which sends the customer back to review', function () {
        $customer = QuickBooksCustomer::factory()->create(['qb_id' => '1']);
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '1', 'qb_linked_at' => now()])->save();

        Livewire::test(ManageQuickBooksCustomers::class)
            ->set('activeTab', 'linked')
            ->callAction(TestAction::make('unlink')->table($customer));

        expect($client->refresh())->qb_id->toBeNull()->realm_id->toBeNull()->qb_linked_at->toBeNull()
            ->and(QuickBooksCustomer::query()->needsReview()->sole()->is($customer))->toBeTrue()
            ->and(Activity::query()->where('event', 'quickbooks')->sole()->description)->toBe('Unlinked from QuickBooks customer 1');
    });

    it('shows a client’s QuickBooks customer on the client’s page', function () {
        QuickBooksCustomer::factory()->create(['qb_id' => '1', 'display_name' => 'Lee Rentals (QB)']);
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '1', 'qb_linked_at' => now()])->save();

        $this->get(ClientResource::getUrl('view', ['record' => $client]))
            ->assertOk()
            ->assertSee('Lee Rentals (QB)');
    });
});

it('forgets a company’s customers, items, documents, and links with it', function () {
    $this->actingAs(User::factory()->superAdmin()->withMfa()->create());
    $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);
    QuickBooksCustomer::factory()->count(2)->create();
    $client = Client::factory()->create();
    $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '1', 'qb_linked_at' => now()])->save();
    $item = CatalogItem::factory()->create(['realm_id' => '9130350000000001']);
    $part = Part::factory()->create(['catalog_item_id' => $item->getKey()]);
    Estimate::factory()->create();
    Invoice::factory()->create()->delete();
    Payment::factory()->create()->applications()->create(['txn_type' => 'Invoice', 'txn_qb_id' => '1', 'amount' => '10.00']);
    SyncIssues::raise(SyncIssueKind::SweepFailed, 'Broken');

    Livewire::test(QuickBooks::class)->callAction(TestAction::make('forget'));

    expect(QuickBooksConnection::query()->exists())->toBeFalse()
        ->and(QuickBooksCustomer::query()->exists())->toBeFalse()
        ->and(CatalogItem::query()->exists())->toBeFalse()
        ->and($part->refresh()->catalog_item_id)->toBeNull()
        ->and($client->refresh())->qb_id->toBeNull()->realm_id->toBeNull()
        ->and(Estimate::withTrashed()->exists() || Invoice::withTrashed()->exists() || Payment::withTrashed()->exists())->toBeFalse()
        ->and(PaymentApplication::query()->exists())->toBeFalse()
        ->and(QuickBooksSyncIssue::query()->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'quickbooks')->latest('id')->value('description'))
        ->toBe('Forgot QuickBooks company 9130350000000001: unlinked 1 client, removed 2 customers, 1 item and 3 documents');
});
