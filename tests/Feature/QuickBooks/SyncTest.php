<?php

use App\Enums\SyncIssueKind;
use App\Filament\Pages\QuickBooks;
use App\Filament\Resources\QuickBooksSyncIssues\Pages\ManageQuickBooksSyncIssues;
use App\Jobs\SweepQuickBooksChanges;
use App\Jobs\SyncQuickBooksEntity;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\SyncIssues;
use Filament\Actions\Testing\TestAction;
use Illuminate\Console\Scheduling\Schedule;
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
 * A URL pattern for the factory company's QuickBooks API.
 */
function qbApi(string $path): string
{
    return 'sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/'.$path;
}

function runSweep(): void
{
    app()->call([new SweepQuickBooksChanges, 'handle']);
}

/**
 * QuickBooks' change data capture answer, with its clock.
 *
 * @param  array<string, list<array<string, mixed>>>  $changes
 */
function fakeChanges(array $changes, string $time = '2026-10-29T08:00:00-07:00'): void
{
    Http::fake([qbApi('cdc*') => Http::response([
        'time' => $time,
        'CDCResponse' => [['QueryResponse' => array_map(fn (string $entity, array $records): array => [$entity => $records], array_keys($changes), $changes)]],
    ])]);
}

function qbRecord(string $id, array $extra = []): array
{
    return ['Id' => $id, 'SyncToken' => '0', 'MetaData' => ['LastUpdatedTime' => '2026-10-29T07:30:00-07:00'], ...$extra];
}

function qbNotFound(): array
{
    return ['Fault' => ['Error' => [['Message' => 'Object Not Found', 'Detail' => 'Object Not Found : Something you’re trying to use has been made inactive.', 'code' => '610']]]];
}

describe('pulling one record', function () {
    it('stores it as QuickBooks has it now, and resolves its open issue', function () {
        Http::fake([qbApi('invoice/20*') => Http::response(['Invoice' => qbRecord('20', ['CustomerRef' => ['value' => '1'], 'TotalAmt' => 100, 'Balance' => 25])])]);
        SyncIssues::raise(SyncIssueKind::PullFailed, 'Earlier failure', 'Invoice', '20');

        app(EntitySync::class)->pull($this->connection, 'Invoice', '20');

        expect(Invoice::query()->sole()->balance)->toBe('25.00')
            ->and(QuickBooksSyncIssue::query()->sole()->isOpen())->toBeFalse();
    });

    it('soft-deletes a document QuickBooks no longer has', function () {
        Invoice::factory()->create(['qb_id' => '20']);
        Http::fake([qbApi('invoice/20*') => Http::response(qbNotFound(), 400)]);

        app(EntitySync::class)->pull($this->connection, 'Invoice', '20');

        expect(Invoice::query()->exists())->toBeFalse()
            ->and(Invoice::withTrashed()->exists())->toBeTrue();
    });

    it('refreshes the invoices a payment pays, before and after it changed', function () {
        Queue::fake();
        $payment = Payment::factory()->create(['qb_id' => '30']);
        $payment->applications()->create(['txn_type' => 'Invoice', 'txn_qb_id' => '19', 'amount' => '50.00']);
        Http::fake([qbApi('payment/30*') => Http::response(['Payment' => qbRecord('30', [
            'CustomerRef' => ['value' => '1'], 'TotalAmt' => 50,
            'Line' => [['Amount' => 50, 'LinkedTxn' => [['TxnId' => '20', 'TxnType' => 'Invoice']]]],
        ])])]);

        app(EntitySync::class)->pull($this->connection, 'Payment', '30');

        Queue::assertPushed(SyncQuickBooksEntity::class, 2);
        Queue::assertPushed(SyncQuickBooksEntity::class, fn (SyncQuickBooksEntity $job): bool => $job->entity === 'Invoice' && $job->qbId === '19');
        Queue::assertPushed(SyncQuickBooksEntity::class, fn (SyncQuickBooksEntity $job): bool => $job->entity === 'Invoice' && $job->qbId === '20');
    });

    it('becomes one sync issue when it keeps failing, and tells super admins once', function () {
        $owner = User::factory()->owner()->create();
        $job = new SyncQuickBooksEntity('Invoice', '20');

        $job->failed(new QuickBooksException('QuickBooks answered 500: Broken', 500, null, 'tid-1'));
        $job->failed(new QuickBooksException('QuickBooks answered 500: Still broken', 500, null, 'tid-2'));

        expect(QuickBooksSyncIssue::query()->sole())
            ->kind->toBe(SyncIssueKind::PullFailed)
            ->occurrences->toBe(2)
            ->intuit_tid->toBe('tid-2')
            ->message->toBe('QuickBooks Invoice 20 didn’t sync: QuickBooks answered 500: Still broken')
            ->and($this->admin->notifications()->count())->toBe(1)
            ->and($owner->notifications()->count())->toBe(0);
    });

    it('is one job per record while it waits', function () {
        expect((new SyncQuickBooksEntity('Invoice', '20'))->uniqueId())->toBe('Invoice:20');
    });
});

describe('the hourly sweep', function () {
    it('brings in what changed since the last check, customers first, and moves the cursor to QuickBooks’ clock', function () {
        $this->connection->update(['changes_synced_at' => '2026-10-29 14:00:00']);
        $client = Client::factory()->create(['email' => 'pat@example.com', 'phone' => null]);
        Estimate::factory()->create(['qb_id' => '7']);
        fakeChanges([
            // For a sub-customer that arrives in the same answer: it has to be stored first to find its client.
            'Invoice' => [qbRecord('20', ['CustomerRef' => ['value' => '42'], 'TotalAmt' => 100, 'Balance' => 100])],
            'Estimate' => [['Id' => '7', 'status' => 'Deleted']],
            'Customer' => [
                qbRecord('41', ['DisplayName' => 'Pat Lee', 'PrimaryEmailAddr' => ['Address' => 'pat@example.com']]),
                qbRecord('42', ['DisplayName' => 'Pat Lee:12 Oak St', 'Job' => true, 'ParentRef' => ['value' => '41']]),
            ],
            'Item' => [qbRecord('3', ['Name' => 'Labor', 'Type' => 'Service'])],
        ]);

        runSweep();

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'entities=Customer,Item,Estimate,Invoice,Payment&changedSince=2026-10-29T13:55:00+00:00'));
        expect(Invoice::query()->sole()->client?->is($client))->toBeTrue()
            ->and($client->refresh()->qb_id)->toBe('41')
            ->and(CatalogItem::query()->sole()->name)->toBe('Labor')
            ->and(Estimate::query()->exists())->toBeFalse()
            ->and($this->connection->refresh()->changes_synced_at->toIso8601String())->toBe('2026-10-29T15:00:00+00:00');
    });

    it('starts from the moment the company connected', function () {
        $this->connection->update(['connected_at' => '2026-10-29 12:00:00', 'changes_synced_at' => null]);
        fakeChanges([]);

        runSweep();

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'changedSince=2026-10-29T11:55:00+00:00'));
    });

    it('unlinks a client whose customer was merged away, and says so', function () {
        QuickBooksCustomer::factory()->create(['qb_id' => '41', 'display_name' => 'Pat Lee']);
        $client = Client::factory()->create(['display_name' => 'Patricia Lee']);
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '41'])->save();
        fakeChanges(['Customer' => [['Id' => '41', 'status' => 'Deleted']]]);

        runSweep();

        expect($client->refresh()->qb_id)->toBeNull()
            ->and(QuickBooksCustomer::query()->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'quickbooks')->sole()->description)->toBe('Unlinked from QuickBooks customer 41: it was deleted or merged in QuickBooks')
            ->and(QuickBooksSyncIssue::query()->sole())
            ->kind->toBe(SyncIssueKind::CustomerDeleted)
            ->message->toContain('Patricia Lee was linked to QuickBooks Pat Lee');
    });

    it('keeps going past a record that won’t store, and raises it', function () {
        fakeChanges(['Invoice' => [['SyncToken' => '0'], qbRecord('21', ['CustomerRef' => ['value' => '1'], 'TotalAmt' => 10, 'Balance' => 10])]]);

        runSweep();

        expect(Invoice::query()->sole()->qb_id)->toBe('21')
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::PullFailed)
            ->and($this->connection->refresh()->changes_synced_at)->not->toBeNull();
    });

    it('says what was missed when the last check is further back than QuickBooks reports', function () {
        $this->travelTo('2026-10-29 12:00:00');
        $this->connection->update(['changes_synced_at' => '2026-09-01 12:00:00']);
        fakeChanges([]);

        runSweep();

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'changedSince=2026-09-29T12:55:00+00:00'));
        expect(QuickBooksSyncIssue::query()->sole())->kind->toBe(SyncIssueKind::ChangesMissed);
    });

    it('says when QuickBooks reported as many changes as it gives at once', function () {
        fakeChanges(['Estimate' => array_map(fn (int $id): array => ['Id' => (string) $id, 'status' => 'Deleted'], range(1, SweepQuickBooksChanges::MAX_CHANGES))]);

        runSweep();

        expect(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::TooManyChanges);
    });

    it('does nothing without a connected company', function () {
        Http::fake();
        $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);

        runSweep();

        Http::assertNothingSent();
    });

    it('becomes a sync issue when it fails', function () {
        (new SweepQuickBooksChanges)->failed(new QuickBooksException('QuickBooks answered 503: Down', 503, null, 'tid-9'));

        expect(QuickBooksSyncIssue::query()->sole())->kind->toBe(SyncIssueKind::SweepFailed)->intuit_tid->toBe('tid-9');
    });

    it('runs every hour, and on demand from the QuickBooks page', function () {
        Queue::fake();

        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->description, SweepQuickBooksChanges::class));
        expect($event?->expression)->toBe('0 * * * *');

        Livewire::test(QuickBooks::class)->callAction(TestAction::make('sweep'));
        Queue::assertPushed(SweepQuickBooksChanges::class);
    });
});

describe('sync issues page', function () {
    it('lists open issues for super admins, with Retry for a record that didn’t sync', function () {
        $pull = SyncIssues::raise(SyncIssueKind::PullFailed, 'Broken', 'Invoice', '20');
        $sweep = SyncIssues::raise(SyncIssueKind::SweepFailed, 'Down');
        $resolved = SyncIssues::raise(SyncIssueKind::CustomerDeleted, 'Gone', 'Customer', '41');
        SyncIssues::resolve($resolved, $this->admin);

        Livewire::test(ManageQuickBooksSyncIssues::class)
            ->assertCanSeeTableRecords([$pull, $sweep])
            ->assertCanNotSeeTableRecords([$resolved])
            ->assertActionVisible(TestAction::make('retry')->table($pull))
            ->assertActionHidden(TestAction::make('retry')->table($sweep));
    });

    it('retries a record and resolves its issue when it syncs', function () {
        $issue = SyncIssues::raise(SyncIssueKind::PullFailed, 'Broken', 'Invoice', '20');
        Http::fake([qbApi('invoice/20*') => Http::response(['Invoice' => qbRecord('20', ['CustomerRef' => ['value' => '1'], 'TotalAmt' => 10, 'Balance' => 0])])]);

        Livewire::test(ManageQuickBooksSyncIssues::class)
            ->callAction(TestAction::make('retry')->table($issue))
            ->assertNotified('Synced Invoice 20');

        expect($issue->refresh()->isOpen())->toBeFalse()
            ->and(Invoice::query()->sole()->balance)->toBe('0.00');
    });

    it('says so when a retry fails, and leaves the issue open', function () {
        $issue = SyncIssues::raise(SyncIssueKind::PullFailed, 'Broken', 'Invoice', '20');
        Http::fake([qbApi('invoice/20*') => Http::response(['Fault' => ['Error' => [['Message' => 'Down', 'code' => '500']]]], 500)]);

        Livewire::test(ManageQuickBooksSyncIssues::class)
            ->callAction(TestAction::make('retry')->table($issue))
            ->assertNotified('Still didn’t sync');

        expect($issue->refresh()->isOpen())->toBeTrue();
    });

    it('resolves an issue someone has dealt with', function () {
        $issue = SyncIssues::raise(SyncIssueKind::CustomerDeleted, 'Gone', 'Customer', '41');

        Livewire::test(ManageQuickBooksSyncIssues::class)->callAction(TestAction::make('resolve')->table($issue));

        expect($issue->refresh())->isOpen()->toBeFalse()->resolved_by->toBe($this->admin->getKey());
    });

    it('is for super admins only', function () {
        $this->actingAs(User::factory()->owner()->withMfa()->create());

        $this->get(ManageQuickBooksSyncIssues::getUrl())->assertForbidden();
    });
});
