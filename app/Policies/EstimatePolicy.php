<?php

namespace App\Policies;

use App\Policies\Concerns\LetsAdminsView;

/**
 * QuickBooks estimates, mirrored read-only: changed in QuickBooks, never here.
 */
class EstimatePolicy
{
    use LetsAdminsView;
}
