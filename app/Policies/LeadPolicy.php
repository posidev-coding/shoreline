<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsAdminsToManageContent;

/**
 * Leads come only from the website's quote form. Admins can delete one, for a request to erase someone's details.
 */
class LeadPolicy
{
    use AllowsAdminsToManageContent;

    public function create(User $user): bool
    {
        return false;
    }
}
