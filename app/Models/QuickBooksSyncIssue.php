<?php

namespace App\Models;

use App\Enums\SyncIssueKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A QuickBooks sync problem that needs a person, shown under Settings → Sync issues. Raised and
 * resolved through App\Services\QuickBooks\SyncIssues, which keeps one open row per problem.
 *
 * @property SyncIssueKind $kind
 * @property string|null $entity
 * @property string|null $qb_id
 * @property int $occurrences
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 */
#[Fillable(['realm_id', 'kind', 'entity', 'qb_id', 'message', 'intuit_tid', 'last_seen_at'])]
#[Table('quickbooks_sync_issues')]
class QuickBooksSyncIssue extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'occurrences' => 1,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    protected function casts(): array
    {
        return [
            'kind' => SyncIssueKind::class,
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
