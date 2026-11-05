<?php

namespace App\Models;

use App\Enums\EstimateStatus;
use App\Models\Concerns\MirrorsQuickBooksDocument;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A QuickBooks estimate, mirrored read-only: App\Services\QuickBooks\DocumentMirror writes it,
 * and a soft delete means it was deleted in QuickBooks. Not in the activity log, since QuickBooks
 * keeps its history.
 *
 * @property string $qb_id
 * @property string $client_qb_id
 * @property array<string, mixed> $qb_payload
 * @property EstimateStatus $status
 * @property Carbon|null $txn_date
 * @property Carbon|null $expiration_date
 * @property string $total_amt
 */
#[Fillable([
    'realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_payload', 'customer_qb_id', 'client_qb_id',
    'customer_name', 'doc_number', 'txn_date', 'expiration_date', 'status', 'accepted_by', 'accepted_date',
    'total_amt', 'total_tax',
])]
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use HasFactory, MirrorsQuickBooksDocument, SoftDeletes;

    public const string QUICKBOOKS_PAGE = 'estimate';

    /**
     * The job it was sent for, once the app sends estimates (M6).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'qb_payload' => 'array',
            'qb_last_updated_at' => 'datetime',
            'txn_date' => 'date',
            'expiration_date' => 'date',
            'accepted_date' => 'date',
            'status' => EstimateStatus::class,
            'total_amt' => 'decimal:2',
            'total_tax' => 'decimal:2',
        ];
    }
}
