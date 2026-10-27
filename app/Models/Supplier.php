<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where parts come from: a distributor, a manufacturer's dealer desk, or the local supply house.
 */
#[Fillable(['name', 'rep_name', 'rep_phone', 'rep_email', 'account_number', 'website', 'notes'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    /**
     * @return HasMany<ProjectLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ProjectLineItem::class);
    }
}
