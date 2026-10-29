<?php

use App\Enums\QuickBooksEnvironment;
use App\Models\QuickBooksConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A pretend sandbox: answers each call the command makes, and records what was asked.
 *
 * @param  array<string, int>  $failing  path => status to fail with
 */
function fakeSandbox(array $failing = []): void
{
    Http::fake(function (Request $request) use ($failing) {
        $path = (string) str(parse_url($request->url(), PHP_URL_PATH))->after('/v3/company/')->after('/');
        $query = urldecode((string) parse_url($request->url(), PHP_URL_QUERY));
        $body = $request->data();

        foreach ($failing as $failingPath => $status) {
            if (str_starts_with($path, $failingPath) && $request->method() === 'POST') {
                return Http::response(['Fault' => ['Error' => [['Message' => 'Business Validation Error', 'Detail' => "{$failingPath} refused", 'code' => '6000']]]], $status, ['intuit_tid' => 'tid-fail']);
            }
        }

        return Http::response(match (true) {
            str_starts_with($path, 'companyinfo') => ['CompanyInfo' => ['CompanyName' => 'Sandbox Company_US_1']],
            $path === 'preferences' => ['Preferences' => ['SalesFormsPrefs' => ['CustomTxnNumbers' => false, 'AllowOnlineCreditCardPayment' => true], 'TaxPrefs' => ['PartnerTaxEnabled' => true]]],
            $path === 'query' && str_contains($query, 'Active IN') => ['QueryResponse' => ['totalCount' => 12]],
            $path === 'query' && str_contains($query, 'Active = false') => ['QueryResponse' => ['totalCount' => 2]],
            $path === 'query' && str_contains($query, 'count(*)') => ['QueryResponse' => ['totalCount' => 10]],
            $path === 'query' => ['QueryResponse' => ['Item' => [['Id' => '1', 'Name' => 'Services', 'Type' => 'Service']]]],
            $path === 'customer' && isset($body['sparse']) => ['Customer' => ['Id' => $body['Id'], 'Active' => false]],
            $path === 'customer' => ['Customer' => ['Id' => str_contains($body['DisplayName'], ':') ? '58' : '59', 'DisplayName' => $body['DisplayName'], 'SyncToken' => '0']],
            str_starts_with($path, 'customer/') => ['Customer' => ['Id' => str($path)->after('/')->toString(), 'SyncToken' => '0']],
            str_contains($query, 'operation=delete') => [ucfirst($path) => ['status' => 'Deleted']],
            str_contains($query, 'operation=void') => ['Invoice' => ['Id' => '200', 'SyncToken' => '1']],
            $path === 'cdc' => ['time' => '2026-10-29T10:00:00-07:00', 'CDCResponse' => [['QueryResponse' => [
                ['Customer' => [['Id' => '59', 'SyncToken' => '1']]],
                ['Invoice' => [['Id' => '200', 'status' => 'Deleted']]],
            ]]]],
            $path === 'estimate' && isset($body['sparse']) => ['Estimate' => ['Id' => '100', 'SyncToken' => '1', 'TxnStatus' => 'Accepted']],
            $path === 'estimate' => ['Estimate' => [
                'Id' => '100', 'SyncToken' => '0', 'DocNumber' => '1001', 'TotalAmt' => 106.75,
                'TxnTaxDetail' => ['TotalTax' => 6.75, 'TxnTaxCodeRef' => ['value' => '3'], 'TaxLine' => [['TaxLineDetail' => ['TaxPercent' => 4.75]], ['TaxLineDetail' => ['TaxPercent' => 2]]]],
            ]],
            $path === 'estimate/100' => ['Estimate' => ['Id' => '100', 'SyncToken' => '1', 'TxnStatus' => 'Accepted', 'AcceptedBy' => 'Shoreline test', 'AcceptedDate' => '2026-10-28']],
            $path === 'invoice' => ['Invoice' => ['Id' => '200', 'SyncToken' => '0']],
            $path === 'invoice/200' && str_contains($query, 'invoiceLink') => ['Invoice' => ['Id' => '200', 'SyncToken' => '0', 'InvoiceLink' => 'https://connect.intuit.com/portal/app/CommerceNetwork/view/scs-v1-abc']],
            $path === 'invoice/200' => ['Invoice' => ['Id' => '200', 'SyncToken' => '1', 'TotalAmt' => 0, 'Balance' => 0, 'PrivateNote' => 'Voided']],
            default => throw new RuntimeException("Unexpected sandbox call: {$request->method()} {$path}?{$query}"),
        });
    });
}

it('asks the sandbox each open question, prints the answers, and tidies up', function () {
    QuickBooksConnection::factory()->create();
    fakeSandbox();

    $this->artisan('app:quickbooks-sandbox-test')
        ->assertSuccessful()
        ->expectsOutputToContain('Sandbox Company_US_1')
        ->expectsOutputToContain('Matched 12 (plain query: 10 active, 2 inactive)')
        ->expectsOutputToContain('Accepted: “Shoreline test: colon')
        ->expectsOutputToContain('TotalAmt 106.75, TotalTax 6.75, TxnTaxCodeRef 3, rates 4.75%, 2%')
        ->expectsOutputToContain('Assigned: 1001')
        ->expectsOutputToContain('Read back: TxnStatus Accepted, AcceptedBy Shoreline test, AcceptedDate 2026-10-28')
        ->expectsOutputToContain('Present, on connect.intuit.com')
        ->expectsOutputToContain("Read back: TotalAmt 0, Balance 0, PrivateNote 'Voided'")
        ->expectsOutputToContain("Server time 2026-10-29T17:00:00+00:00; 1 Customer, 1 Invoice; test invoice listed with status 'Deleted'")
        ->expectsOutputToContain('Not testable by API');

    // The documents are deleted and both test customers deactivated.
    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), '/invoice?operation=delete'));
    Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), '/estimate?operation=delete'));
    expect(Http::recorded(fn (Request $request): bool => ($request->data()['Active'] ?? null) === false))->toHaveCount(2);
});

it('keeps going when one check fails, and says why', function () {
    QuickBooksConnection::factory()->create();
    fakeSandbox(['estimate' => 400]);

    $this->artisan('app:quickbooks-sandbox-test')
        ->assertSuccessful()
        ->expectsOutputToContain('estimate refused (intuit_tid tid-fail)')
        ->expectsOutputToContain('Present, on connect.intuit.com');
});

it('leaves the test records when asked to', function () {
    QuickBooksConnection::factory()->create();
    fakeSandbox();

    $this->artisan('app:quickbooks-sandbox-test --keep')->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'operation=delete'));
});

it('only runs against a sandbox company', function () {
    Http::fake();
    QuickBooksConnection::factory()->create(['environment' => QuickBooksEnvironment::Production]);

    $this->artisan('app:quickbooks-sandbox-test')->assertFailed()->expectsOutputToContain('only runs against a sandbox company');

    Http::assertNothingSent();
});

it('needs a connected company', function () {
    Http::fake();

    $this->artisan('app:quickbooks-sandbox-test')->assertFailed()->expectsOutputToContain('isn’t connected');

    Http::assertNothingSent();
});
