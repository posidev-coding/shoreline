<?php

namespace App\Models;

use App\Enums\QuickBooksEnvironment;
use Database\Factories\QuickBooksConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The app's link to one QuickBooks company, with its OAuth tokens (encrypted, and cleared on
 * disconnect). App\Services\QuickBooks\TokenManager is the only thing that writes the tokens.
 * Not in the activity log: its tokens are encrypted, and connecting and disconnecting are
 * logged as events instead.
 *
 * @property QuickBooksEnvironment $environment
 * @property string $realm_id
 * @property string|null $company_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $access_expires_at
 * @property Carbon|null $refresh_expires_at
 * @property Carbon|null $refresh_hard_expires_at
 * @property Carbon|null $last_refreshed_at
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $preferences_checked_at
 * @property Carbon|null $connected_at
 * @property Carbon|null $disconnected_at
 * @property string|null $disconnect_reason
 * @property Carbon|null $changes_synced_at
 */
#[Fillable([
    'environment', 'realm_id', 'company_name', 'access_token', 'refresh_token', 'access_expires_at',
    'refresh_expires_at', 'refresh_hard_expires_at', 'last_refreshed_at', 'preferences', 'preferences_checked_at',
    'connected_by', 'connected_at', 'disconnected_at', 'disconnect_reason', 'changes_synced_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
// Not quick_books_connections, which is what Laravel would make of the class name.
#[Table('quickbooks_connections')]
class QuickBooksConnection extends Model
{
    /** @use HasFactory<QuickBooksConnectionFactory> */
    use HasFactory;

    /**
     * The connection, connected or not. There's at most one.
     */
    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }

    /**
     * Sandbox or production, worked out once a request, for links into QuickBooks.
     */
    public static function environmentInUse(): ?QuickBooksEnvironment
    {
        return once(fn (): ?QuickBooksEnvironment => self::current()?->environment);
    }

    public function isConnected(): bool
    {
        return $this->disconnected_at === null && filled($this->refresh_token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    protected function casts(): array
    {
        return [
            'environment' => QuickBooksEnvironment::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'refresh_hard_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'preferences' => 'array',
            'changes_synced_at' => 'datetime',
            'preferences_checked_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }
}
