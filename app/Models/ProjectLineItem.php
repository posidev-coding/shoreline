<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Database\Factories\ProjectLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of the project's working draft, which becomes a QuickBooks estimate line in M6.
 */
#[Fillable(['project_id', 'sort_order', 'description', 'quantity', 'unit_price', 'is_taxable'])]
class ProjectLineItem extends Model
{
    /** @use HasFactory<ProjectLineItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quantity times rate, rounded half up to the cent, as QuickBooks rounds a line's amount.
     */
    public function amount(): Money
    {
        return Money::of(
            BigDecimal::of($this->unit_price)->multipliedBy($this->quantity),
            'USD',
            roundingMode: RoundingMode::HalfUp,
        );
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'is_taxable' => 'boolean',
        ];
    }
}
