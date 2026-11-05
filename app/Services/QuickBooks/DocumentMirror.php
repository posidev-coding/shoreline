<?php

namespace App\Services\QuickBooks;

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\QuickBooksCustomer;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stores QuickBooks estimates, invoices, and payments as read-only mirrors, keyed by realm and
 * QuickBooks id, and soft-deletes the ones deleted in QuickBooks. An older copy of a document
 * never overwrites a newer one.
 */
final class DocumentMirror
{
    public const array ENTITIES = ['Estimate', 'Invoice', 'Payment'];

    /**
     * How deep QuickBooks nests sub-customers.
     */
    private const int MAX_CUSTOMER_DEPTH = 5;

    /**
     * @param  array<string, mixed>  $document
     */
    public function store(string $realmId, string $entity, array $document): Estimate|Invoice|Payment
    {
        return match ($entity) {
            'Estimate' => $this->estimate($realmId, $document),
            'Invoice' => $this->invoice($realmId, $document),
            'Payment' => $this->payment($realmId, $document),
            default => throw new InvalidArgumentException("QuickBooks {$entity} isn't mirrored."),
        };
    }

    /**
     * Soft-deletes a document deleted in QuickBooks, and returns it, or null when it was never
     * mirrored.
     */
    public function delete(string $realmId, string $entity, string $qbId): Estimate|Invoice|Payment|null
    {
        $document = $this->query($entity)->where('realm_id', $realmId)->where('qb_id', $qbId)->first();

        if ($document instanceof Payment) {
            // A deleted payment no longer pays anything.
            $document->applications()->delete();
        }

        $document?->delete();

        return $document;
    }

    /**
     * @param  array<string, mixed>  $estimate
     */
    private function estimate(string $realmId, array $estimate): Estimate
    {
        /** @var Estimate $row */
        $row = $this->row($realmId, 'Estimate', $estimate);

        if (QuickBooksRecord::isOlderThan($estimate, $row->qb_last_updated_at)) {
            return $row;
        }

        $tax = data_get($estimate, 'TxnTaxDetail.TotalTax');

        $row->fill([
            ...$this->shared($realmId, $estimate),
            'doc_number' => $estimate['DocNumber'] ?? null,
            'txn_date' => $estimate['TxnDate'] ?? null,
            'expiration_date' => $estimate['ExpirationDate'] ?? null,
            'status' => EstimateStatus::fromQuickBooks($estimate['TxnStatus'] ?? null),
            'accepted_by' => $estimate['AcceptedBy'] ?? null,
            'accepted_date' => $estimate['AcceptedDate'] ?? null,
            'total_amt' => QuickBooksRecord::money($estimate['TotalAmt'] ?? 0),
            'total_tax' => $tax === null ? null : QuickBooksRecord::money($tax),
        ])->save();

        return $row;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoice(string $realmId, array $invoice): Invoice
    {
        /** @var Invoice $row */
        $row = $this->row($realmId, 'Invoice', $invoice);

        if (QuickBooksRecord::isOlderThan($invoice, $row->qb_last_updated_at)) {
            return $row;
        }

        $total = QuickBooksRecord::money($invoice['TotalAmt'] ?? 0);
        $estimate = collect((array) ($invoice['LinkedTxn'] ?? []))->first(fn (mixed $link): bool => data_get($link, 'TxnType') === 'Estimate');

        $row->fill([
            ...$this->shared($realmId, $invoice),
            'estimate_qb_id' => data_get($estimate, 'TxnId'),
            'doc_number' => $invoice['DocNumber'] ?? null,
            'txn_date' => $invoice['TxnDate'] ?? null,
            'due_date' => $invoice['DueDate'] ?? null,
            'total_amt' => $total,
            'balance' => QuickBooksRecord::money($invoice['Balance'] ?? 0),
            // Voiding keeps the invoice but zeroes it and notes "Voided" in its PrivateNote.
            'is_voided' => BigDecimal::of($total)->isZero() && str_contains((string) ($invoice['PrivateNote'] ?? ''), 'Voided'),
        ])->save();

        return $row;
    }

    /**
     * A payment and its lines: each line applies an amount to one or more transactions.
     *
     * @param  array<string, mixed>  $payment
     */
    private function payment(string $realmId, array $payment): Payment
    {
        /** @var Payment $row */
        $row = $this->row($realmId, 'Payment', $payment);

        if (QuickBooksRecord::isOlderThan($payment, $row->qb_last_updated_at)) {
            return $row;
        }

        DB::transaction(function () use ($row, $realmId, $payment): void {
            $unapplied = $payment['UnappliedAmt'] ?? null;

            $row->fill([
                ...$this->shared($realmId, $payment),
                'txn_date' => $payment['TxnDate'] ?? null,
                'reference' => $payment['PaymentRefNum'] ?? null,
                'total_amt' => QuickBooksRecord::money($payment['TotalAmt'] ?? 0),
                'unapplied_amt' => $unapplied === null ? null : QuickBooksRecord::money($unapplied),
            ])->save();

            $row->applications()->delete();

            foreach ((array) ($payment['Line'] ?? []) as $line) {
                foreach ((array) data_get($line, 'LinkedTxn', []) as $link) {
                    $row->applications()->create([
                        'txn_type' => (string) data_get($link, 'TxnType'),
                        'txn_qb_id' => (string) data_get($link, 'TxnId'),
                        'amount' => QuickBooksRecord::money(data_get($line, 'Amount')),
                    ]);
                }
            }
        });

        return $row;
    }

    /**
     * The mirrored row for the document, trashed or not, or a new one.
     *
     * @param  array<string, mixed>  $document
     */
    private function row(string $realmId, string $entity, array $document): Model
    {
        return $this->query($entity)->withTrashed()->firstOrNew(['realm_id' => $realmId, 'qb_id' => (string) $document['Id']]);
    }

    /**
     * The fields every mirrored document has.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function shared(string $realmId, array $document): array
    {
        $customer = (string) data_get($document, 'CustomerRef.value');

        return [
            'qb_sync_token' => $document['SyncToken'] ?? null,
            'qb_last_updated_at' => QuickBooksRecord::updatedAt($document),
            'qb_payload' => $document,
            'customer_qb_id' => $customer,
            'client_qb_id' => $this->topCustomer($realmId, $customer),
            'customer_name' => data_get($document, 'CustomerRef.name'),
        ];
    }

    /**
     * The top-level customer a customer sits under (itself, when it isn't a sub-customer), from
     * the imported customers. A customer not imported yet counts as top-level until the
     * document is pulled again.
     */
    private function topCustomer(string $realmId, string $customerQbId): string
    {
        $qbId = $customerQbId;

        for ($depth = 0; $depth < self::MAX_CUSTOMER_DEPTH; $depth++) {
            $parent = QuickBooksCustomer::query()->where('realm_id', $realmId)->where('qb_id', $qbId)->value('parent_qb_id');

            if ($parent === null) {
                break;
            }

            $qbId = (string) $parent;
        }

        return $qbId;
    }

    /**
     * @return Builder<Estimate>|Builder<Invoice>|Builder<Payment>
     */
    private function query(string $entity): Builder
    {
        return match ($entity) {
            'Estimate' => Estimate::query(),
            'Invoice' => Invoice::query(),
            'Payment' => Payment::query(),
            default => throw new InvalidArgumentException("QuickBooks {$entity} isn't mirrored."),
        };
    }
}
