<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

/**
 * QuickBooks items, viewed in the admin and imported from QuickBooks. Nothing here edits them:
 * the resource has no forms or record actions.
 */
class CatalogItemPolicy
{
    use AllowsAdmins;
}
