<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A QuickBooks item's Type. Lines bill as Service or Non-inventory items only (v1): a category
 * only groups items, a bundle (Group) expands into its own lines, and inventory needs QBO's
 * stock tracking, which the app does itself.
 */
enum CatalogItemType: string implements HasLabel
{
    case Service = 'Service';
    case NonInventory = 'NonInventory';
    case Inventory = 'Inventory';
    case Category = 'Category';
    case Group = 'Group';
    /** Any type QuickBooks adds later; its raw type stays in qb_payload. */
    case Other = 'Other';

    public function isBillable(): bool
    {
        return in_array($this, [self::Service, self::NonInventory], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::NonInventory => 'Non-inventory',
            self::Inventory => 'Inventory',
            self::Category => 'Category',
            self::Group => 'Bundle',
            self::Other => 'Other',
        };
    }
}
