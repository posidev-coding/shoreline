<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\PartStockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a part's stock. Written only by Stock, and never edited or deleted.
 */
#[Fillable(['part_id', 'type', 'quantity', 'balance_after', 'project_id', 'project_line_item_id', 'user_id', 'note'])]
class PartStockMovement extends Model
{
    /** @use HasFactory<PartStockMovementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProjectLineItem, $this>
     */
    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(ProjectLineItem::class, 'project_line_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }
}
