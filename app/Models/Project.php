<?php

namespace App\Models;

use App\Enums\ProjectStage;
use App\Support\Stock;
use Brick\Money\Money;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One job at one property. Its client always follows the property, so the two can't disagree.
 * Photos stay on the private disk; the quote request's own photos stay on the lead.
 *
 * @property-read Client $client
 * @property-read Property $property
 */
#[Fillable(['property_id', 'service_id', 'lead_id', 'title', 'stage', 'lost_reason', 'description'])]
class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stage' => 'estimating',
    ];

    /**
     * Trashed too, so a trashed project still shows whose it was.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<ProjectLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ProjectLineItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('starts_at');
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest()->latest('id');
    }

    /**
     * What this job installed, as recorded when it was completed.
     *
     * @return HasMany<InstalledEquipment, $this>
     */
    public function installedEquipment(): HasMany
    {
        return $this->hasMany(InstalledEquipment::class, 'installed_by_project_id');
    }

    /**
     * The line items' total before tax, which QuickBooks adds.
     */
    public function subtotal(): Money
    {
        return $this->lineItems()->get()->reduce(
            fn (Money $total, ProjectLineItem $item): Money => $total->plus($item->amount()),
            Money::zero('USD'),
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photos')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(75);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('stage', ProjectStage::finished());
    }

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            if ($project->isDirty('property_id')) {
                $project->client_id = Property::withTrashed()->whereKey($project->property_id)->valueOrFail('client_id');
            }

            if ($project->isDirty('stage')) {
                $project->stage_changed_at = now();
                $project->completed_at = match ($project->stage) {
                    ProjectStage::Completed, ProjectStage::Closed => $project->completed_at ?? now(),
                    default => null,
                };

                if ($project->stage !== ProjectStage::Lost) {
                    $project->lost_reason = null;
                }
            }
        });

        // Finishing the job uses its stocked parts. Each line is deducted once, so reopening
        // and completing again only deducts lines added since.
        static::saved(function (self $project): void {
            $justCompleted = $project->wasRecentlyCreated ? $project->completed_at !== null : $project->wasChanged('completed_at');

            if ($justCompleted && $project->completed_at !== null) {
                app(Stock::class)->useOnProject($project);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'stage_changed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
