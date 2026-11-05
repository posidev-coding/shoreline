<?php

namespace App\Services\QuickBooks;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use App\Models\QuickBooksConnection;

/**
 * Pulls the company's items into catalog_items, active and inactive, keyed by realm and QuickBooks
 * id, so running it again only updates. QuickBooks never deletes an item (it makes it inactive),
 * so nothing here is ever removed.
 */
final class ItemImporter
{
    /**
     * @return array{created: int, updated: int}
     */
    public function import(QuickBooksConnection $connection): array
    {
        $client = QuickBooksClient::for($connection);
        $counts = ['created' => 0, 'updated' => 0];

        // A plain query returns only active items; inactive ones need asking for.
        foreach (['', 'where Active = false'] as $where) {
            foreach ($client->queryAll('Item', $where) as $item) {
                $model = $this->store($connection->realm_id, $item);

                if ($model->wasRecentlyCreated) {
                    $counts['created']++;
                } elseif ($model->wasChanged()) {
                    $counts['updated']++;
                }
            }
        }

        return $counts;
    }

    /**
     * Stores one item, from the import, the hourly sweep, or a pull of that item.
     *
     * @param  array<string, mixed>  $item
     */
    public function store(string $realmId, array $item): CatalogItem
    {
        $model = CatalogItem::query()->firstOrNew(['realm_id' => $realmId, 'qb_id' => (string) $item['Id']]);

        if (! QuickBooksRecord::isOlderThan($item, $model->qb_last_updated_at)) {
            $model->fill($this->attributes($item))->save();
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributes(array $item): array
    {
        return [
            'qb_sync_token' => $item['SyncToken'] ?? null,
            'qb_last_updated_at' => QuickBooksRecord::updatedAt($item),
            'qb_payload' => $item,
            'name' => (string) ($item['Name'] ?? ''),
            'fully_qualified_name' => (string) ($item['FullyQualifiedName'] ?? $item['Name'] ?? ''),
            'type' => CatalogItemType::tryFrom((string) ($item['Type'] ?? '')) ?? CatalogItemType::Other,
            'parent_qb_id' => data_get($item, 'ParentRef.value'),
            'sku' => $item['Sku'] ?? null,
            'description' => $item['Description'] ?? null,
            'unit_price' => is_numeric($item['UnitPrice'] ?? null) ? (string) $item['UnitPrice'] : null,
            'is_taxable' => (bool) ($item['Taxable'] ?? false),
            'income_account_qb_id' => data_get($item, 'IncomeAccountRef.value'),
            'income_account_name' => data_get($item, 'IncomeAccountRef.name'),
            'is_active' => (bool) ($item['Active'] ?? true),
        ];
    }
}
