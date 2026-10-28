<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A QuickBooks item (a product or service), mirrored from QuickBooks and never edited here.
 * App\Services\QuickBooks\ItemImporter writes it.
 *
 * @property CatalogItemType $type
 */
#[Fillable([
    'realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_payload', 'name', 'fully_qualified_name',
    'type', 'parent_qb_id', 'sku', 'description', 'unit_price', 'is_taxable', 'income_account_qb_id',
    'income_account_name', 'is_active',
])]
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory;

    /**
     * The items a part or a line can bill as, as Select options: active Service and
     * Non-inventory items, by full name.
     *
     * @return array<int, string>
     */
    public static function billableOptions(): array
    {
        return self::query()
            ->billable()
            ->orderBy('fully_qualified_name')
            ->pluck('fully_qualified_name', 'id')
            ->all();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function billable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereIn('type', array_map(fn (CatalogItemType $type): string => $type->value, array_filter(CatalogItemType::cases(), fn (CatalogItemType $type): bool => $type->isBillable())));
    }

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'qb_payload' => 'array',
            'qb_last_updated_at' => 'datetime',
            'unit_price' => 'decimal:4',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
