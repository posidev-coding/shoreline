<?php

namespace App\Models;

use App\Enums\County;
use App\Enums\PropertyType;
use App\Models\Concerns\CascadesSoftDeletes;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A place the work happens. One client can have many, like a property manager's rentals.
 *
 * The access info (lockbox and gate codes) is encrypted, and stays out of the activity log:
 * the log stores changed values as they read, so it records only that the codes changed.
 *
 * @property-read Client $client
 */
#[Fillable([
    'client_id', 'label', 'street', 'city', 'state', 'zip', 'county', 'service_area_id', 'type',
    'access_info', 'site_contact_name', 'site_contact_phone', 'turnover_notes', 'notes',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use CascadesSoftDeletes, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The column defaults, set on the model too, so the first change logged isn't a phantom
     * one from a default the model hadn't loaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => 'NC',
        'type' => 'primary',
    ];

    /**
     * Trashed too, so a trashed property still shows whose it was.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<InstalledEquipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(InstalledEquipment::class);
    }

    /**
     * "12 Front St, Beaufort, NC 28516"
     */
    public function address(): string
    {
        return "{$this->street}, {$this->city}, {$this->state} {$this->zip}";
    }

    public function mapUrl(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->address());
    }

    /**
     * Whether this is the address someone typed, ignoring case, spacing, and punctuation.
     */
    public function isAt(string $street, string $zip): bool
    {
        $normalize = fn (string $value): string => preg_replace('/[^a-z0-9]/', '', mb_strtolower($value)) ?? '';

        return $normalize($this->street) === $normalize($street) && trim($this->zip) === trim($zip);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logExcept(['access_info'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        // Equipment isn't trashed with the property, but goes when the property is deleted for good.
        static::forceDeleting(function (self $property): void {
            $property->equipment()->whereNull('parent_id')->get()->each->delete();
        });

        static::updated(function (self $property): void {
            if ($property->wasChanged('access_info')) {
                activity()
                    ->performedOn($property)
                    ->event('updated')
                    ->withChanges(['attributes' => ['access_info' => 'Changed'], 'old' => ['access_info' => 'Hidden']])
                    ->log('Changed the access info');
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function softDeleteCascades(): array
    {
        return ['projects'];
    }

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'county' => County::class,
            'access_info' => 'encrypted',
        ];
    }
}
