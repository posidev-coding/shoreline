<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of a payment went to one transaction, usually an invoice (txn_type Invoice), as
 * QuickBooks reports it on the payment's lines.
 *
 * @property string $txn_type
 * @property string $txn_qb_id
 * @property string $amount
 */
#[Fillable(['payment_id', 'txn_type', 'txn_qb_id', 'amount'])]
class PaymentApplication extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The invoice it paid, when it paid one and that invoice is mirrored.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'txn_qb_id', 'qb_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
