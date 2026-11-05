<?php

namespace App\Services\QuickBooks;

use App\Enums\SyncIssueKind;
use App\Jobs\SyncQuickBooksEntity;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\PaymentApplication;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use InvalidArgumentException;

/**
 * Brings one QuickBooks record into the app: customers and items into their imports, and
 * estimates, invoices, and payments into their mirrors. A record deleted in QuickBooks is
 * soft-deleted here; a deleted customer's client is unlinked, and an item goes inactive.
 */
final class EntitySync
{
    /**
     * What the app follows in QuickBooks, customers first so documents find the customer they
     * belong to.
     */
    public const array ENTITIES = ['Customer', 'Item', 'Estimate', 'Invoice', 'Payment'];

    public function __construct(
        private CustomerImporter $customers,
        private ItemImporter $items,
        private DocumentMirror $documents,
        private CustomerLinker $linker,
    ) {}

    /**
     * Fetches the record as QuickBooks has it now, so it doesn't matter in what order changes
     * are reported.
     */
    public function pull(QuickBooksConnection $connection, string $entity, string $qbId): void
    {
        self::ensureFollowed($entity);

        try {
            $record = QuickBooksClient::for($connection)->read($entity, $qbId);
        } catch (QuickBooksException $exception) {
            if (! $exception->isNotFound()) {
                throw $exception;
            }

            $record = ['Id' => $qbId, 'status' => 'Deleted'];
        }

        $this->apply($connection, $entity, $record);

        SyncIssues::resolveFor($entity, $qbId);
    }

    /**
     * Stores a record already in hand, from the hourly sweep or a pull. A deleted one comes as
     * its Id with status Deleted. A payment also queues a pull of each invoice it pays, or paid
     * before it changed, for their balances.
     *
     * @param  array<string, mixed>  $record
     */
    public function apply(QuickBooksConnection $connection, string $entity, array $record): void
    {
        self::ensureFollowed($entity);

        $qbId = (string) $record['Id'];

        if ($entity !== 'Payment') {
            $this->store($connection->realm_id, $entity, $qbId, $record);

            return;
        }

        $paidBefore = $this->paidInvoices($connection->realm_id, $qbId);
        $this->store($connection->realm_id, $entity, $qbId, $record);

        foreach (array_unique([...$paidBefore, ...$this->paidInvoices($connection->realm_id, $qbId)]) as $invoiceQbId) {
            SyncQuickBooksEntity::dispatch('Invoice', $invoiceQbId);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function store(string $realmId, string $entity, string $qbId, array $record): void
    {
        if (($record['status'] ?? null) !== 'Deleted') {
            match ($entity) {
                'Customer' => $this->customers->store($realmId, $record),
                'Item' => $this->items->store($realmId, $record),
                default => $this->documents->store($realmId, $entity, $record),
            };

            return;
        }

        match ($entity) {
            'Customer' => $this->customerDeleted($realmId, $qbId),
            // QuickBooks makes items inactive rather than deleting them, so a deleted one is treated the same.
            'Item' => CatalogItem::query()->where('realm_id', $realmId)->where('qb_id', $qbId)->update(['is_active' => false]),
            default => $this->documents->delete($realmId, $entity, $qbId),
        };
    }

    /**
     * A customer is deleted when it's merged into another. Its client, if it had one, is
     * unlinked, and a super admin is told to link the client to the customer that's left.
     */
    private function customerDeleted(string $realmId, string $qbId): void
    {
        $customer = QuickBooksCustomer::query()->where('realm_id', $realmId)->where('qb_id', $qbId)->first();
        $client = Client::withTrashed()->where('realm_id', $realmId)->where('qb_id', $qbId)->first();
        $name = $customer->display_name ?? "customer {$qbId}";

        if ($client !== null) {
            $this->linker->unlink($client, null, 'it was deleted or merged in QuickBooks');

            SyncIssues::raise(
                SyncIssueKind::CustomerDeleted,
                "{$client->display_name} was linked to QuickBooks {$name}, which was deleted or merged into another customer in QuickBooks. Link the client again under QuickBooks customers.",
                'Customer',
                $qbId,
            );
        }

        $customer?->delete();
    }

    /**
     * @return list<string>
     */
    private function paidInvoices(string $realmId, string $paymentQbId): array
    {
        return array_values(PaymentApplication::query()
            ->where('txn_type', 'Invoice')
            ->whereHas('payment', fn ($payment) => $payment->withTrashed()->where('realm_id', $realmId)->where('qb_id', $paymentQbId))
            ->pluck('txn_qb_id')
            ->map(fn (mixed $qbId): string => (string) $qbId)
            ->all());
    }

    private static function ensureFollowed(string $entity): void
    {
        if (! in_array($entity, self::ENTITIES, true)) {
            throw new InvalidArgumentException("The app doesn't follow QuickBooks {$entity}.");
        }
    }
}
