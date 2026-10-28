<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'calendar_feed_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOneTimePasswords, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * `role` is deliberately not fillable: it decides admin access, so set it explicitly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Client->value,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->canAccessAdmin();
    }

    /**
     * The clients whose portal this person can sign in to. A trashed client drops out.
     *
     * @return BelongsToMany<Client, $this, ClientUser, 'access'>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class)
            ->using(ClientUser::class)
            ->as('access')
            ->withPivot(['id', 'client_role', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * A client's person with at least one portal to sign in to. Admins use /admin instead.
     */
    public function canUsePortal(): bool
    {
        return $this->role === UserRole::Client && $this->clients()->exists();
    }

    /**
     * Admins can view the portal as one of its users (stechstudio/filament-impersonate).
     */
    public function canImpersonate(): bool
    {
        return $this->role->canAccessAdmin();
    }

    /**
     * Only portal users: an admin is never signed in as another admin.
     */
    public function canBeImpersonated(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'calendar_feed_read_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
        ];
    }

    /**
     * Who hears about new quote requests: the owner, or super admins until an owner account
     * exists, so a lead is never announced to nobody.
     *
     * @return Collection<int, self>
     */
    public static function leadRecipients(): Collection
    {
        $owners = self::query()->where('role', UserRole::Owner)->get();

        return $owners->isNotEmpty() ? $owners : self::query()->where('role', UserRole::SuperAdmin)->get();
    }

    /**
     * Stored lowercase to match the unique index on lower(email).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }
}
