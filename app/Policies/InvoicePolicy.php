<?php

namespace App\Policies;

use App\Policies\Concerns\LetsAdminsView;

/**
 * QuickBooks invoices, mirrored read-only: changed in QuickBooks, never here.
 */
class InvoicePolicy
{
    use LetsAdminsView;
}
