<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use Database\Factories\InstalledEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What's installed at a property, as a tree: a door, with its springs, opener, and hardware
 * under it. Answers "what spring is on this door?" and drives maintenance reminders (M7).
 *
 * @property-read Property $property
 */
#[Fillable([
    'property_id', 'parent_id', 'type', 'part_id', 'manufacturer', 'model', 'serial', 'specs',
    'size', 'material', 'design_pressure_positive', 'design_pressure_negative', 'is_impact_rated',
    'installed_on', 'installed_by_project_id', 'warranty_expires_on', 'last_serviced_on', 'maintenance_due_on',
    'status', 'replaced_by_id', 'notes',
])]
class InstalledEquipment extends Model implements HasMedia
{
    /** @use HasFactory<InstalledEquipmentFactory> */
    use HasFactory, InteractsWithMedia;

    protected $table = 'installed_equipment';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'specs' => '{}',
        'status' => 'active',
        'is_impact_rated' => false,
    ];

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

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
    public function installedByProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'installed_by_project_id')->withTrashed();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /**
     * "Door · Clopay 4050 · 16x7", for lists and pickers.
     */
    public function title(): string
    {
        return collect([
            $this->type->getLabel(),
            trim($this->manufacturer.' '.$this->model) ?: null,
            $this->size,
        ])->filter()->implode(' · ');
    }

    /**
     * Retires this piece in favor of a newer one, keeping the link between them.
     */
    public function replaceWith(self $replacement): void
    {
        $this->update(['status' => EquipmentStatus::Replaced, 'replaced_by_id' => $replacement->getKey()]);
    }

    protected static function booted(): void
    {
        // One by one, not by the database's cascade, so each child's label photo is deleted too.
        static::deleting(function (self $equipment): void {
            $equipment->children()->get()->each->delete();
        });
    }

    public function registerMediaCollections(): void
    {
        // A photo of the door's design-pressure label, the proof of its wind rating.
        $this->addMediaCollection('label')->useDisk('private')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('label')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(75);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', EquipmentStatus::Active);
    }

    protected function casts(): array
    {
        return [
            'type' => EquipmentType::class,
            'status' => EquipmentStatus::class,
            'specs' => 'array',
            'design_pressure_positive' => 'decimal:1',
            'design_pressure_negative' => 'decimal:1',
            'is_impact_rated' => 'boolean',
            'installed_on' => 'date',
            'warranty_expires_on' => 'date',
            'last_serviced_on' => 'date',
            'maintenance_due_on' => 'date',
        ];
    }
}
