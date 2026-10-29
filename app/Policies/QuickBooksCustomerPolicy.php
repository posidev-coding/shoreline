<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

/**
 * QuickBooks customers, imported from QuickBooks and linked to clients in the admin. Nothing here
 * edits them: the resource has no forms, only the linking actions.
 */
class QuickBooksCustomerPolicy
{
    use AllowsAdmins;
}
