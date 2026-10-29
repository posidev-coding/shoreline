<?php

namespace App\Models;

use Database\Factories\QuickBooksCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * A QuickBooks customer as last imported, waiting to be matched to a client or already matched.
 * The match is the client's qb_id, so there's one place it's kept.
 * App\Services\QuickBooks\CustomerImporter writes these; CustomerLinker matches them.
 *
 * @property string $realm_id
 * @property string $qb_id
 * @property array<string, mixed> $qb_payload
 * @property Carbon|null $skipped_at
 * @property-read Client|null $client
 * @property-read QuickBooksCustomer|null $parent
 */
#[Fillable([
    'realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_payload', 'display_name', 'company_name',
    'given_name', 'family_name', 'email', 'phone', 'billing_city', 'is_active', 'parent_qb_id', 'is_project',
])]
#[Table('quickbooks_customers')]
class QuickBooksCustomer extends Model
{
    /** @use HasFactory<QuickBooksCustomerFactory> */
    use HasFactory;

    /**
     * The client it's matched to, even one in the trash. By QuickBooks id alone: the app is
     * linked to one company at a time, and forgetting one clears its matches.
     *
     * @return HasOne<Client, $this>
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'qb_id', 'qb_id')->withTrashed();
    }

    /**
     * The customer a sub-customer or project sits under.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_qb_id', 'qb_id');
    }

    public function isSubCustomer(): bool
    {
        return $this->parent_qb_id !== null;
    }

    /**
     * Top-level customers nobody has matched or skipped: the ones the owner reviews.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needsReview(Builder $query): void
    {
        $query->whereNull('parent_qb_id')->whereNull('skipped_at')->whereNotExists($this->matchingClient(...));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function linked(Builder $query): void
    {
        $query->whereExists($this->matchingClient(...));
    }

    private function matchingClient(QueryBuilder $client): void
    {
        $client->from('clients')
            ->whereColumn('clients.qb_id', 'quickbooks_customers.qb_id')
            ->whereColumn('clients.realm_id', 'quickbooks_customers.realm_id');
    }

    protected function casts(): array
    {
        return [
            'qb_payload' => 'array',
            'qb_last_updated_at' => 'datetime',
            'is_active' => 'boolean',
            'is_project' => 'boolean',
            'skipped_at' => 'datetime',
        ];
    }
}
