<?php

namespace App\Models;

use App\Models\Concerns\MirrorsQuickBooksDocument;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A payment received in QuickBooks, mirrored read-only with what it was applied to:
 * App\Services\QuickBooks\DocumentMirror writes it, and a soft delete means it was deleted in
 * QuickBooks.
 *
 * @property string $qb_id
 * @property string $client_qb_id
 * @property array<string, mixed> $qb_payload
 * @property Carbon|null $txn_date
 * @property string $total_amt
 */
#[Fillable([
    'realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_payload', 'customer_qb_id', 'client_qb_id',
    'customer_name', 'txn_date', 'reference', 'total_amt', 'unapplied_amt',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, MirrorsQuickBooksDocument, SoftDeletes;

    public const string QUICKBOOKS_PAGE = 'recvpayment';

    /**
     * @return HasMany<PaymentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    protected function casts(): array
    {
        return [
            'qb_payload' => 'array',
            'qb_last_updated_at' => 'datetime',
            'txn_date' => 'date',
            'total_amt' => 'decimal:2',
            'unapplied_amt' => 'decimal:2',
        ];
    }
}
