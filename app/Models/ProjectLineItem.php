<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ProjectStage;
use App\Models\Concerns\BelongsToProject;
use App\Support\Stock;
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
 *
 * A line can name a part from the catalog, and say whether that part has to be ordered for
 * the job. Ordering one moves an approved project to "Materials ordered", and receiving a
 * stocked part puts it into stock until the job is completed and uses it.
 *
 * @property-read Project $project
 */
#[Fillable([
    'project_id', 'part_id', 'sort_order', 'description', 'quantity', 'unit_price', 'is_taxable',
    'order_status', 'supplier_id', 'expected_on',
])]
class ProjectLineItem extends Model
{
    /** @use HasFactory<ProjectLineItemFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Who it's ordered from: usually the part's supplier, but it can differ for one order.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (! $item->isDirty('order_status')) {
                return;
            }

            // Stamped when the order moves forward, and cleared when it's moved back by mistake.
            $item->ordered_at = match ($item->order_status) {
                OrderStatus::Ordered, OrderStatus::Received => $item->ordered_at ?? now(),
                default => null,
            };
            $item->received_at = $item->order_status === OrderStatus::Received ? ($item->received_at ?? now()) : null;
        });

        static::saved(function (self $item): void {
            if (! $item->wasChanged('order_status') && ! $item->wasRecentlyCreated) {
                return;
            }

            if ($item->order_status === OrderStatus::Ordered || $item->order_status === OrderStatus::Received) {
                // Ordering parts is what an approved job waits on next. Later stages stay put.
                $item->project()->where('stage', ProjectStage::Approved)->first()?->update(['stage' => ProjectStage::MaterialsOrdered]);
            }

            if ($item->order_status === OrderStatus::Received) {
                app(Stock::class)->receiveForLineItem($item);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'is_taxable' => 'boolean',
            'order_status' => OrderStatus::class,
            'expected_on' => 'date',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
