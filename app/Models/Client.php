<?php

namespace App\Models;

use App\Enums\ProjectStage;
use App\Models\Concerns\CascadesSoftDeletes;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Who the work is for, and who gets the bill: a homeowner, or a property manager with many
 * properties. Becomes a QuickBooks customer in M6.
 */
#[Fillable([
    'display_name', 'first_name', 'last_name', 'company', 'email', 'phone',
    'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'is_property_manager', 'notes',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use CascadesSoftDeletes, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The column defaults, set on the model too, so the first change logged isn't a phantom
     * one from a default the model hadn't loaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_property_manager' => false,
    ];

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The people who can sign in to this client's portal.
     *
     * @return BelongsToMany<User, $this, ClientUser, 'access'>
     */
    public function portalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ClientUser::class)
            ->as('access')
            ->withPivot(['id', 'client_role', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function openProjects(): HasMany
    {
        return $this->projects()->whereNotIn('stage', ProjectStage::finished());
    }

    /**
     * Clients who might be the same person: the same email, ignoring case, or the same phone.
     *
     * @return Builder<self>
     */
    public static function matching(?string $email, ?string $phone): Builder
    {
        return self::query()
            ->where(fn (Builder $query) => $query
                ->when(filled($email), fn (Builder $query) => $query->orWhereRaw('lower(email) = ?', [mb_strtolower(trim((string) $email))]))
                ->when(filled($phone), fn (Builder $query) => $query->orWhere('phone', $phone))
                ->when(blank($email) && blank($phone), fn (Builder $query) => $query->whereRaw('false')));
    }

    /**
     * Whether another client, trashed ones included, already uses this display name. QuickBooks
     * keeps inactive customers' names reserved too.
     */
    public static function displayNameTaken(string $name, ?int $ignoring = null): bool
    {
        return self::withTrashed()
            ->whereRaw('lower(display_name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoring !== null, fn (Builder $query) => $query->whereKeyNot($ignoring))
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return list<string>
     */
    protected function softDeleteCascades(): array
    {
        return ['projects', 'properties'];
    }

    /**
     * Stored lowercase, so a quote request from the same person matches regardless of case.
     *
     * @return Attribute<?string, ?string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? mb_strtolower(trim($value)) : null);
    }

    protected function casts(): array
    {
        return [
            'is_property_manager' => 'boolean',
        ];
    }
}
