<?php

namespace App\Services\QuickBooks;

use App\Enums\SyncIssueKind;
use App\Jobs\SyncQuickBooksEntity;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\ProjectLineItem;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use Illuminate\Support\Facades\DB;
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
     * Follows a merge in QuickBooks, which reports the record that's left and the one merged into
     * it. QuickBooks is asked first: the merged one must be gone, or nothing moves and a person
     * is asked to look. A customer's client and documents move to the customer that's left, unless
     * that one already has a client; an item's parts and job lines move to the item that's left.
     */
    public function merge(QuickBooksConnection $connection, string $entity, string $survivorQbId, string $mergedQbId): void
    {
        self::ensureFollowed($entity);

        try {
            QuickBooksClient::for($connection)->read($entity, $mergedQbId);
            $mergedIsGone = false;
        } catch (QuickBooksException $exception) {
            if (! $exception->isNotFound()) {
                throw $exception;
            }

            $mergedIsGone = true;
        }

        $this->pull($connection, $entity, $survivorQbId);

        if (! $mergedIsGone) {
            SyncIssues::raise(
                SyncIssueKind::MergeNeedsReview,
                "QuickBooks reported {$entity} {$mergedQbId} merged into {$survivorQbId}, but {$mergedQbId} is still there, so the app moved nothing. Check both in QuickBooks.",
                $entity,
                $mergedQbId,
            );

            return;
        }

        match ($entity) {
            'Customer' => $this->customerMerged($connection->realm_id, $survivorQbId, $mergedQbId),
            'Item' => $this->itemMerged($connection->realm_id, $survivorQbId, $mergedQbId),
            default => $this->apply($connection, $entity, ['Id' => $mergedQbId, 'status' => 'Deleted']),
        };
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
     * QuickBooks moves the merged customer's documents and sub-customers to the one that's left,
     * so they move here too, and so does its client, unless the customer that's left has one.
     */
    private function customerMerged(string $realmId, string $survivorQbId, string $mergedQbId): void
    {
        $survivor = QuickBooksCustomer::query()->where('realm_id', $realmId)->where('qb_id', $survivorQbId)->first();
        $merged = QuickBooksCustomer::query()->where('realm_id', $realmId)->where('qb_id', $mergedQbId)->first();
        $client = Client::withTrashed()->where('realm_id', $realmId)->where('qb_id', $mergedQbId)->first();
        $survivorClient = Client::withTrashed()->where('realm_id', $realmId)->where('qb_id', $survivorQbId)->first();
        $mergedName = $merged->display_name ?? "customer {$mergedQbId}";

        DB::transaction(function () use ($realmId, $survivorQbId, $mergedQbId, $survivor, $merged, $client, $survivorClient, $mergedName): void {
            $clientQbId = $this->documents->clientQbIdFor($realmId, $survivorQbId);

            foreach ([Estimate::class, Invoice::class, Payment::class] as $documents) {
                $documents::withTrashed()->where('realm_id', $realmId)->where('customer_qb_id', $mergedQbId)
                    ->update(['customer_qb_id' => $survivorQbId, 'client_qb_id' => $clientQbId]);
                $documents::withTrashed()->where('realm_id', $realmId)->where('client_qb_id', $mergedQbId)
                    ->update(['client_qb_id' => $clientQbId]);
            }

            QuickBooksCustomer::query()->where('realm_id', $realmId)->where('parent_qb_id', $mergedQbId)->update(['parent_qb_id' => $survivorQbId]);

            if ($client !== null && $survivor !== null && $survivorClient === null) {
                $this->linker->relink($client, $survivor, "{$mergedName} ({$mergedQbId}) was merged into it in QuickBooks");
            } elseif ($client !== null) {
                $this->linker->unlink($client, null, 'it was merged into another customer in QuickBooks');

                SyncIssues::raise(
                    SyncIssueKind::CustomerDeleted,
                    "{$client->display_name} was linked to QuickBooks {$mergedName}, which was merged into ".($survivor->display_name ?? "customer {$survivorQbId}")
                        .($survivorClient ? ", already linked to {$survivorClient->display_name}" : '')
                        .'. Decide which client it is, and link it under QuickBooks customers.',
                    'Customer',
                    $mergedQbId,
                );
            }

            $merged?->delete();
        });
    }

    /**
     * Parts and job lines that bill as the merged item bill as the one that's left.
     */
    private function itemMerged(string $realmId, string $survivorQbId, string $mergedQbId): void
    {
        $survivor = CatalogItem::query()->where('realm_id', $realmId)->where('qb_id', $survivorQbId)->first();
        $merged = CatalogItem::query()->where('realm_id', $realmId)->where('qb_id', $mergedQbId)->first();

        if ($merged === null || $survivor === null) {
            return;
        }

        DB::transaction(function () use ($survivor, $merged): void {
            $parts = Part::query()->where('catalog_item_id', $merged->getKey())->update(['catalog_item_id' => $survivor->getKey()]);
            $lines = ProjectLineItem::query()->where('catalog_item_id', $merged->getKey())->update(['catalog_item_id' => $survivor->getKey()]);
            $merged->delete();

            activity()->event('quickbooks')->log(
                "QuickBooks item {$merged->fully_qualified_name} was merged into {$survivor->fully_qualified_name}: "
                .trans_choice('{0} no parts|{1} 1 part|[2,*] :count parts', $parts).' and '
                .trans_choice('{0} no job lines|{1} 1 job line|[2,*] :count job lines', $lines).' bill as it now',
            );
        });
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
