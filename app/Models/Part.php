<?php

namespace App\Models;

use App\Enums\PartCategory;
use App\Enums\PartUnit;
use Brick\Math\BigDecimal;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Something he installs or sells: door models, openers, springs, and the hardware around them.
 *
 * `on_hand` isn't fillable. It changes only through Stock, which records each change in
 * part_stock_movements, so the count and its history always agree.
 */
#[Fillable([
    'category', 'name', 'manufacturer', 'model', 'sku', 'specs', 'supplier_id', 'unit', 'cost', 'price',
    'warranty_months', 'is_active', 'track_stock', 'reorder_point',
])]
class Part extends Model implements HasMedia
{
    /** @use HasFactory<PartFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity;

    /**
     * The column defaults, set on the model too, so the first change logged isn't a phantom
     * one from a default the model hadn't loaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'specs' => '{}',
        'unit' => 'each',
        'is_active' => true,
        'track_stock' => false,
        'on_hand' => '0.00',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PartStockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(PartStockMovement::class);
    }

    /**
     * @return HasMany<InstalledEquipment, $this>
     */
    public function installedEquipment(): HasMany
    {
        return $this->hasMany(InstalledEquipment::class);
    }

    /**
     * @return HasMany<ProjectLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ProjectLineItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->track_stock
            && $this->reorder_point !== null
            && BigDecimal::of($this->on_hand)->isLessThanOrEqualTo($this->reorder_point);
    }

    /**
     * "12", "2.5 foot", or "1 pair": the count without trailing zeros, with the unit unless it's each.
     */
    public function quantityLabel(string $quantity): string
    {
        $number = (string) BigDecimal::of($quantity)->strippedOfTrailingZeros();

        return $this->unit === PartUnit::Each ? $number : "{$number} {$this->unit->value}";
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->useDisk('private')->singleFile();
        $this->addMediaCollection('spec_sheet')->useDisk('private')->singleFile();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('track_stock', true)
            ->whereNotNull('reorder_point')
            ->whereColumn('on_hand', '<=', 'reorder_point');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'category' => PartCategory::class,
            'unit' => PartUnit::class,
            'specs' => 'array',
            'cost' => 'decimal:4',
            'price' => 'decimal:4',
            'warranty_months' => 'integer',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'on_hand' => 'decimal:2',
            'reorder_point' => 'decimal:2',
        ];
    }
}
