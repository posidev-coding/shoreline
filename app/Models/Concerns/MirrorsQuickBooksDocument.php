<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\QuickBooksConnection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared by the documents mirrored read-only from QuickBooks (estimates, invoices, payments):
 * which client they're for, and a link to the document in QuickBooks. Each model names its
 * QuickBooks page in QUICKBOOKS_PAGE.
 */
trait MirrorsQuickBooksDocument
{
    /**
     * The client linked to the document's customer, or, for a sub-customer's document, to the
     * customer it sits under.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_qb_id', 'qb_id');
    }

    public function quickBooksUrl(): ?string
    {
        return QuickBooksConnection::environmentInUse()?->appUrl(static::QUICKBOOKS_PAGE.'?txnId='.rawurlencode($this->qb_id));
    }
}
