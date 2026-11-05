<?php

use App\Enums\EstimateStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Jobs\ImportQuickBooksDocuments;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use App\Services\QuickBooks\DocumentMirror;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->owner = User::factory()->owner()->withMfa()->create());
    $this->connection = QuickBooksConnection::factory()->create(['realm_id' => '9130350000000001']);
    $this->mirror = app(DocumentMirror::class);
});

/**
 * A QuickBooks document as the API returns it.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function qbDocument(string $id, string $customer = '1', array $extra = []): array
{
    return [
        'Id' => $id, 'SyncToken' => '0', 'DocNumber' => "10{$id}", 'TxnDate' => '2026-10-20',
        'CustomerRef' => ['value' => $customer, 'name' => 'Pat Lee'], 'TotalAmt' => 100,
        'MetaData' => ['LastUpdatedTime' => '2026-10-20T09:15:00-07:00'], ...$extra,
    ];
}

describe('mirroring', function () {
    it('stores an estimate, under the top-level customer of a sub-customer', function () {
        QuickBooksCustomer::factory()->create(['qb_id' => '1']);
        QuickBooksCustomer::factory()->create(['qb_id' => '2', 'parent_qb_id' => '1']);
        QuickBooksCustomer::factory()->create(['qb_id' => '3', 'parent_qb_id' => '2']);

        $estimate = $this->mirror->store('9130350000000001', 'Estimate', qbDocument('7', '3', [
            'TotalAmt' => 106.75, 'TxnStatus' => 'Accepted', 'AcceptedBy' => 'Pat Lee', 'AcceptedDate' => '2026-10-21',
            'ExpirationDate' => '2026-11-20', 'TxnTaxDetail' => ['TotalTax' => 6.75],
        ]));

        expect($estimate->refresh())
            ->customer_qb_id->toBe('3')
            ->client_qb_id->toBe('1')
            ->doc_number->toBe('107')
            ->status->toBe(EstimateStatus::Accepted)
            ->accepted_by->toBe('Pat Lee')
            ->total_amt->toBe('106.75')
            ->total_tax->toBe('6.75')
            ->expiration_date->toDateString()->toBe('2026-11-20')
            ->and($this->mirror->store('9130350000000001', 'Estimate', qbDocument('8'))->status)->toBe(EstimateStatus::Pending)
            ->and($this->mirror->store('9130350000000001', 'Estimate', qbDocument('9', extra: ['TxnStatus' => 'Expired']))->status)->toBe(EstimateStatus::Other);
    });

    it('stores an invoice with the estimate it came from, and tells a voided one', function () {
        $invoice = $this->mirror->store('9130350000000001', 'Invoice', qbDocument('20', extra: [
            'Balance' => 40, 'DueDate' => '2026-11-19',
            'LinkedTxn' => [['TxnId' => '5', 'TxnType' => 'ReimburseCharge'], ['TxnId' => '7', 'TxnType' => 'Estimate']],
        ]));
        $voided = $this->mirror->store('9130350000000001', 'Invoice', qbDocument('21', extra: ['TotalAmt' => 0, 'Balance' => 0, 'PrivateNote' => 'Voided - wrong customer']));

        expect($invoice->refresh())
            ->estimate_qb_id->toBe('7')
            ->total_amt->toBe('100.00')
            ->balance->toBe('40.00')
            ->is_voided->toBeFalse()
            ->and($voided->refresh()->is_voided)->toBeTrue()
            ->and($voided->status())->toBe(InvoiceStatus::Voided);
    });

    it('works out where an invoice stands', function (string $balance, ?string $due, InvoiceStatus $status) {
        $this->travelTo('2026-10-29 12:00:00');

        expect(Invoice::factory()->make(['total_amt' => '100.00', 'balance' => $balance, 'due_date' => $due])->status())->toBe($status);
    })->with([
        'open' => ['100.00', '2026-11-28', InvoiceStatus::Open],
        'due today' => ['100.00', '2026-10-29', InvoiceStatus::Open],
        'overdue' => ['100.00', '2026-10-28', InvoiceStatus::Overdue],
        'partly paid' => ['40.00', '2026-10-28', InvoiceStatus::PartlyPaid],
        'paid' => ['0.00', '2026-10-28', InvoiceStatus::Paid],
    ]);

    it('stores a payment with what it paid, replacing them when it changes', function () {
        $this->mirror->store('9130350000000001', 'Payment', qbDocument('30', extra: [
            'TotalAmt' => 150, 'UnappliedAmt' => 10, 'PaymentRefNum' => '1042',
            'Line' => [
                ['Amount' => 100, 'LinkedTxn' => [['TxnId' => '20', 'TxnType' => 'Invoice']]],
                ['Amount' => 40, 'LinkedTxn' => [['TxnId' => '21', 'TxnType' => 'Invoice']]],
            ],
        ]));
        $payment = $this->mirror->store('9130350000000001', 'Payment', qbDocument('30', extra: [
            'SyncToken' => '1', 'TotalAmt' => 150, 'MetaData' => ['LastUpdatedTime' => '2026-10-21T09:15:00-07:00'],
            'Line' => [['Amount' => 150, 'LinkedTxn' => [['TxnId' => '20', 'TxnType' => 'Invoice']]]],
        ]));

        expect($payment->refresh())->reference->toBeNull()->total_amt->toBe('150.00')
            ->and($payment->applications->map->only(['txn_type', 'txn_qb_id', 'amount'])->all())
            ->toBe([['txn_type' => 'Invoice', 'txn_qb_id' => '20', 'amount' => '150.00']]);
    });

    it('keeps the newer copy when an older one arrives late', function () {
        $this->mirror->store('9130350000000001', 'Invoice', qbDocument('20', extra: ['Balance' => 0, 'MetaData' => ['LastUpdatedTime' => '2026-10-21T09:15:00-07:00']]));
        $this->mirror->store('9130350000000001', 'Invoice', qbDocument('20', extra: ['Balance' => 100]));

        expect(Invoice::query()->sole()->balance)->toBe('0.00');
    });

    it('soft-deletes a document deleted in QuickBooks, and a payment stops paying', function () {
        $this->mirror->store('9130350000000001', 'Payment', qbDocument('30', extra: ['Line' => [['Amount' => 100, 'LinkedTxn' => [['TxnId' => '20', 'TxnType' => 'Invoice']]]]]));

        $this->mirror->delete('9130350000000001', 'Payment', '30');

        expect(Payment::query()->exists())->toBeFalse()
            ->and(Payment::withTrashed()->sole()->applications()->exists())->toBeFalse()
            ->and($this->mirror->delete('9130350000000001', 'Invoice', 'never-seen'))->toBeNull();
    });
});

describe('import', function () {
    it('brings in the last year’s documents and older invoices still owed, a page per job', function () {
        $this->travelTo('2026-10-29 12:00:00');
        Http::fake(['sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/query*' => function (Request $request) {
            $query = urldecode($request->url());

            return Http::response(['QueryResponse' => match (true) {
                str_contains($query, 'from Estimate') => ['Estimate' => [qbDocument('7')]],
                str_contains($query, "Balance > '0'") => ['Invoice' => [qbDocument('19', extra: ['TxnDate' => '2024-02-01', 'Balance' => 100])]],
                str_contains($query, 'from Invoice') => ['Invoice' => [qbDocument('20', extra: ['Balance' => 0])]],
                str_contains($query, 'from Payment') => ['Payment' => [qbDocument('30')]],
            }]);
        }]);

        ImportQuickBooksDocuments::dispatchSync($this->owner);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), "select * from Estimate where TxnDate >= '2025-10-29' startposition 1 maxresults 200"));
        expect(Estimate::query()->count())->toBe(1)
            ->and(Invoice::query()->pluck('qb_id')->sort()->values()->all())->toBe(['19', '20'])
            ->and(Payment::query()->count())->toBe(1)
            ->and($this->owner->notifications()->sole()->data['body'])->toBe('4 from QuickBooks, dated from Oct 29, 2025, plus older invoices still owed.');
    });

    it('asks for the next page while a page comes back full', function () {
        Queue::fake();
        Http::fake(['sandbox-quickbooks.api.intuit.com/*' => Http::response(['QueryResponse' => [
            'Invoice' => array_map(fn (int $id): array => qbDocument((string) $id), range(1, ImportQuickBooksDocuments::PAGE_SIZE)),
        ]])]);

        app()->call([new ImportQuickBooksDocuments(since: '2025-10-29', step: 1), 'handle']);

        Queue::assertPushed(ImportQuickBooksDocuments::class, fn (ImportQuickBooksDocuments $job): bool => $job->step === 1 && $job->start === 201 && $job->since === '2025-10-29');
    });

    it('runs from the invoices page, only once QuickBooks is connected', function () {
        Queue::fake();

        Livewire::test(ListInvoices::class)->callAction(TestAction::make('import'));
        Queue::assertPushed(ImportQuickBooksDocuments::class);

        $this->connection->update(['disconnected_at' => now(), 'refresh_token' => null]);
        Livewire::test(ListInvoices::class)->assertActionHidden(TestAction::make('import'));
    });
});

describe('admin', function () {
    it('lists unpaid invoices first, with a link to each in QuickBooks', function () {
        $unpaid = Invoice::factory()->create(['qb_id' => '20']);
        $paid = Invoice::factory()->paid()->create();
        $voided = Invoice::factory()->create(['total_amt' => '0.00', 'balance' => '0.00', 'is_voided' => true]);

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid, $voided])
            ->assertActionHasUrl(TestAction::make('openInQuickBooks')->table($unpaid), 'https://app.sandbox.qbo.intuit.com/app/invoice?txnId=20')
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$unpaid, $paid, $voided]);
    });

    it('lists payments with the invoices they paid', function () {
        $invoice = Invoice::factory()->create(['qb_id' => '20', 'doc_number' => '1020']);
        $payment = Payment::factory()->create();
        $payment->applications()->create(['txn_type' => 'Invoice', 'txn_qb_id' => '20', 'amount' => '100.00']);
        $payment->applications()->create(['txn_type' => 'CreditMemo', 'txn_qb_id' => '9', 'amount' => '-10.00']);

        Livewire::test(ListPayments::class)
            ->assertTableColumnStateSet('paid', "Invoice {$invoice->doc_number}, CreditMemo 9", $payment);
    });

    it('shows a linked client’s invoices on the client’s page, sub-customers’ included', function () {
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '1'])->save();
        $theirs = Invoice::factory()->create(['customer_qb_id' => '3', 'client_qb_id' => '1']);
        $someoneElses = Invoice::factory()->create(['client_qb_id' => '2']);

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$someoneElses]);

        expect(InvoicesRelationManager::canViewForRecord(Client::factory()->create(), ViewClient::class))->toBeFalse();
    });

    it('is for admins only', function () {
        $this->actingAs(User::factory()->create());

        $this->get(ListInvoices::getUrl())->assertForbidden();
    });
});
