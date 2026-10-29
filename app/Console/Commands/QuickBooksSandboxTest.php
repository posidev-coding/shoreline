<?php

namespace App\Console\Commands;

use App\Enums\QuickBooksEnvironment;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncLog;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Asks the connected sandbox company what the plan couldn't confirm from Intuit's docs (PLAN.md,
 * "Unverified"), and prints a table to paste back. It creates two test customers, an estimate,
 * and an invoice, voids the invoice, then deletes the documents and deactivates the customers,
 * and asks change data capture how it reports them. Sandbox only.
 */
#[Signature('app:quickbooks-sandbox-test {--keep : Leave the test records in the sandbox}')]
#[Description('Run the QuickBooks sandbox checks and print what the sandbox says')]
class QuickBooksSandboxTest extends Command
{
    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $rows = [];

    /**
     * What the checks made, to tidy up afterwards.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $made = [];

    private QuickBooksClient $client;

    public function handle(): int
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            $this->error('QuickBooks isn’t connected. Connect the sandbox company from Settings → QuickBooks first.');

            return self::FAILURE;
        }

        if ($connection->environment !== QuickBooksEnvironment::Sandbox) {
            $this->error('This writes test records, so it only runs against a sandbox company.');

            return self::FAILURE;
        }

        $this->client = QuickBooksClient::for($connection);
        $stamp = now()->format('YmdHis');

        $this->check('Company', fn (): string => (string) $this->client->refreshCompanyDetails()->company_name);
        $this->preferences($connection);
        $this->check('Hard-expiry field in token response', fn (): string => (string) (QuickBooksSyncLog::query()
            ->where('path', 'oauth2/v1/tokens/bearer')->where('status', 200)->latest('id')->value('detail') ?? 'No token call logged yet'));
        $this->check('Query: Active IN (true, false)', fn (): string => $this->activeInQuery());
        $this->check('Colon in DisplayName', fn (): string => 'Accepted: “'.$this->createCustomer("Shoreline test: colon {$stamp}", 'colon')['DisplayName'].'”');
        $this->check('Test customer', fn (): string => 'Created '.$this->createCustomer("Shoreline test {$stamp}", 'customer')['Id']);

        $item = $this->check('Service item for lines', fn (): string => $this->serviceItem());

        if (isset($this->made['customer']) && $item !== null) {
            $this->check('Estimate with NC ShipAddr (AST)', fn (): string => $this->estimate());
            $this->check('Estimate DocNumber', fn (): string => $this->docNumber('estimate'));
            $this->check('Estimate TxnStatus=Accepted (sparse)', fn (): string => $this->accept());
            $this->check('Invoice include=invoiceLink', fn (): string => $this->invoiceLink());
            $this->check('Invoice DocNumber', fn (): string => $this->docNumber('invoice'));
            $this->check('Invoice void (operation=void)', fn (): string => $this->void());
        }

        $this->rows[] = ['Merge events', 'Not testable by API', 'Merge two customers in the sandbox once webhooks exist (M5d).'];

        if (! $this->option('keep')) {
            $this->tidyUp();

            // After the tidy-up, so the deleted test invoice shows how a deletion is reported.
            if (isset($this->made['invoice']['Id'])) {
                $this->check('Change data capture (CDC)', fn (): string => $this->changes());
            }
        }

        $this->table(['Check', 'Result', 'Detail'], $this->rows);

        return self::SUCCESS;
    }

    /**
     * Runs one check and records its row. Returns the result, or null when it failed.
     *
     * @param  Closure(): string  $check
     */
    private function check(string $name, Closure $check): ?string
    {
        try {
            $detail = $check();
            $this->rows[] = [$name, 'OK', $detail];

            return $detail;
        } catch (QuickBooksException $exception) {
            $this->rows[] = [$name, 'Error', $exception->getMessage().($exception->intuitTid ? " (intuit_tid {$exception->intuitTid})" : '')];

            return null;
        }
    }

    private function preferences(QuickBooksConnection $connection): void
    {
        foreach ([
            'SalesFormsPrefs.CustomTxnNumbers',
            'TaxPrefs.PartnerTaxEnabled',
            'TaxPrefs.UsingSalesTax',
            'SalesFormsPrefs.AllowOnlineCreditCardPayment',
            'SalesFormsPrefs.AllowOnlineACHPayment',
            'SalesFormsPrefs.ETransactionEnabledStatus',
        ] as $key) {
            $value = data_get($connection->fresh()?->preferences, $key);
            $this->rows[] = ["Preference {$key}", 'Read', $value === null ? 'Not reported' : var_export($value, true)];
        }
    }

    private function activeInQuery(): string
    {
        $count = fn (string $where): int => (int) ($this->client->get('query', ['query' => "select count(*) from Customer {$where}"])['QueryResponse']['totalCount'] ?? 0);

        $both = $count('where Active IN (true, false)');

        return "Matched {$both} (plain query: {$count('')} active, {$count('where Active = false')} inactive)";
    }

    /**
     * @return array<string, mixed>
     */
    private function createCustomer(string $displayName, string $key): array
    {
        $customer = (array) ($this->client->post('customer', ['DisplayName' => $displayName])['Customer'] ?? []);
        $this->made[$key] = $customer;

        return $customer;
    }

    private function serviceItem(): string
    {
        $item = $this->client->queryAll('Item', "where Type = 'Service'", pageSize: 1)->first();

        if ($item === null) {
            throw new QuickBooksException('The sandbox has no Service item.');
        }

        $this->made['item'] = $item;

        return "{$item['Name']} ({$item['Id']})";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(): array
    {
        return [[
            'Amount' => 100,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => [
                'ItemRef' => ['value' => $this->made['item']['Id']],
                'Qty' => 1,
                'UnitPrice' => 100,
                'TaxCodeRef' => ['value' => 'TAX'],
            ],
        ]];
    }

    private function estimate(): string
    {
        $estimate = (array) ($this->client->post('estimate', [
            'CustomerRef' => ['value' => $this->made['customer']['Id']],
            'ShipAddr' => ['Line1' => '210 Front St', 'City' => 'Beaufort', 'CountrySubDivisionCode' => 'NC', 'PostalCode' => '28516'],
            'Line' => $this->lines(),
        ])['Estimate'] ?? []);
        $this->made['estimate'] = $estimate;

        $rates = implode(', ', array_map(
            fn (mixed $line): string => (data_get($line, 'TaxLineDetail.TaxPercent') ?? '?').'%',
            (array) data_get($estimate, 'TxnTaxDetail.TaxLine', []),
        ));

        return 'TotalAmt '.($estimate['TotalAmt'] ?? '?')
            .', TotalTax '.(data_get($estimate, 'TxnTaxDetail.TotalTax') ?? 'none')
            .', TxnTaxCodeRef '.(data_get($estimate, 'TxnTaxDetail.TxnTaxCodeRef.value') ?? 'none')
            .($rates !== '' ? ", rates {$rates}" : '');
    }

    private function docNumber(string $key): string
    {
        if (! isset($this->made[$key])) {
            throw new QuickBooksException("No {$key} was created.");
        }

        $docNumber = $this->made[$key]['DocNumber'] ?? null;

        return blank($docNumber) ? 'Blank' : "Assigned: {$docNumber}";
    }

    private function accept(): string
    {
        $estimate = $this->made['estimate'] ?? throw new QuickBooksException('No estimate was created.');

        $updated = (array) ($this->client->post('estimate', [
            'Id' => $estimate['Id'],
            'SyncToken' => $estimate['SyncToken'],
            'sparse' => true,
            'TxnStatus' => 'Accepted',
            'AcceptedBy' => 'Shoreline test',
            'AcceptedDate' => now()->toDateString(),
        ])['Estimate'] ?? []);
        $this->made['estimate'] = $updated;

        $read = $this->client->read('Estimate', (string) $estimate['Id']);

        return 'Read back: TxnStatus '.($read['TxnStatus'] ?? 'none')
            .', AcceptedBy '.($read['AcceptedBy'] ?? 'none')
            .', AcceptedDate '.($read['AcceptedDate'] ?? 'none');
    }

    private function invoiceLink(): string
    {
        $invoice = (array) ($this->client->post('invoice', [
            'CustomerRef' => ['value' => $this->made['customer']['Id']],
            'BillEmail' => ['Address' => 'sandbox-test@example.com'],
            'AllowOnlineCreditCardPayment' => true,
            'AllowOnlineACHPayment' => true,
            'Line' => $this->lines(),
        ])['Invoice'] ?? []);
        $this->made['invoice'] = $invoice;

        $read = (array) ($this->client->get('invoice/'.$invoice['Id'], ['include' => 'invoiceLink'])['Invoice'] ?? []);
        $link = $read['InvoiceLink'] ?? null;

        return $link
            ? 'Present, on '.parse_url((string) $link, PHP_URL_HOST)
            : 'Absent (BillEmail set; online card '.var_export($read['AllowOnlineCreditCardPayment'] ?? null, true).', bank '.var_export($read['AllowOnlineACHPayment'] ?? null, true).')';
    }

    /**
     * Voids the test invoice and reads it back, to see how a voided invoice looks: QuickBooks
     * keeps it, zeroed, and is said to note "Voided" in its PrivateNote.
     */
    private function void(): string
    {
        $invoice = $this->made['invoice'] ?? throw new QuickBooksException('No invoice was created.');

        $this->client->post('invoice', [
            'Id' => $invoice['Id'],
            'SyncToken' => $this->current('invoice')['SyncToken'] ?? $invoice['SyncToken'],
        ], ['operation' => 'void']);

        $read = $this->client->read('Invoice', (string) $invoice['Id']);

        return 'Read back: TotalAmt '.var_export($read['TotalAmt'] ?? null, true)
            .', Balance '.var_export($read['Balance'] ?? null, true)
            .', PrivateNote '.var_export($read['PrivateNote'] ?? null, true);
    }

    /**
     * Asks change data capture for the last half hour, which takes in the test documents.
     */
    private function changes(): string
    {
        $result = $this->client->changesSince(['Customer', 'Estimate', 'Invoice'], now()->subMinutes(30));
        $invoice = collect($result['changes']['Invoice'] ?? [])->firstWhere('Id', $this->made['invoice']['Id']);

        return 'Server time '.($result['time']?->toIso8601String() ?? 'not reported')
            .'; '.(collect($result['changes'])->map(fn (array $records, string $entity): string => count($records)." {$entity}")->implode(', ') ?: 'no changes')
            .'; test invoice '.($invoice === null ? 'not listed' : 'listed with status '.var_export($invoice['status'] ?? null, true));
    }

    /**
     * Deletes the test documents and deactivates the test customers (QuickBooks can't delete one).
     */
    private function tidyUp(): void
    {
        foreach (['invoice', 'estimate'] as $entity) {
            if (isset($this->made[$entity]['Id'])) {
                $this->check("Tidy up: delete {$entity}", fn (): string => (string) ($this->client->post($entity, [
                    'Id' => $this->made[$entity]['Id'],
                    'SyncToken' => $this->current($entity)['SyncToken'] ?? $this->made[$entity]['SyncToken'],
                ], ['operation' => 'delete'])[ucfirst($entity)]['status'] ?? 'Deleted'));
            }
        }

        foreach (['customer', 'colon'] as $key) {
            if (isset($this->made[$key]['Id'])) {
                $this->check('Tidy up: deactivate '.$this->made[$key]['DisplayName'], fn (): string => 'Active '.var_export($this->client->post('customer', [
                    'Id' => $this->made[$key]['Id'],
                    'SyncToken' => $this->current('customer', $this->made[$key]['Id'])['SyncToken'] ?? $this->made[$key]['SyncToken'],
                    'sparse' => true,
                    'Active' => false,
                ])['Customer']['Active'] ?? null, true));
            }
        }
    }

    /**
     * The record as QuickBooks has it now, for its latest SyncToken.
     *
     * @return array<string, mixed>
     */
    private function current(string $entity, ?string $id = null): array
    {
        return $this->client->read(ucfirst($entity), (string) ($id ?? $this->made[$entity]['Id']));
    }
}
