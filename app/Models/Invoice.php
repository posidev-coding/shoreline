<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\MirrorsQuickBooksDocument;
use App\Support\BusinessCalendar;
use Brick\Math\BigDecimal;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A QuickBooks invoice, mirrored read-only: App\Services\QuickBooks\DocumentMirror writes it,
 * and a soft delete means it was deleted in QuickBooks. A voided invoice stays, at zero.
 *
 * @property string $qb_id
 * @property string $client_qb_id
 * @property array<string, mixed> $qb_payload
 * @property Carbon|null $txn_date
 * @property Carbon|null $due_date
 * @property string $total_amt
 * @property string $balance
 * @property bool $is_voided
 */
#[Fillable([
    'realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_payload', 'customer_qb_id', 'client_qb_id',
    'customer_name', 'estimate_qb_id', 'doc_number', 'txn_date', 'due_date', 'total_amt', 'balance', 'is_voided',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, MirrorsQuickBooksDocument, SoftDeletes;

    public const string QUICKBOOKS_PAGE = 'invoice';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_voided' => false,
    ];

    public function status(): InvoiceStatus
    {
        $balance = BigDecimal::of($this->balance);

        return match (true) {
            $this->is_voided => InvoiceStatus::Voided,
            $balance->isLessThanOrEqualTo(0) => InvoiceStatus::Paid,
            $balance->isLessThan($this->total_amt) => InvoiceStatus::PartlyPaid,
            // By calendar date: the due date has no time, and today is Eastern.
            $this->due_date !== null && $this->due_date->toDateString() < BusinessCalendar::today()->toDateString() => InvoiceStatus::Overdue,
            default => InvoiceStatus::Open,
        };
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Not voided, with something still owed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->where('is_voided', false)->where('balance', '>', 0);
    }

    protected function casts(): array
    {
        return [
            'qb_payload' => 'array',
            'qb_last_updated_at' => 'datetime',
            'txn_date' => 'date',
            'due_date' => 'date',
            'total_amt' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_voided' => 'boolean',
        ];
    }
}
