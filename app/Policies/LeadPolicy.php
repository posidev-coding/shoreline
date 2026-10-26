<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsAdmins;

/**
 * Leads come only from the website's quote form. Admins can delete one, for a request to erase someone's details.
 */
class LeadPolicy
{
    use AllowsAdmins;

    public function create(User $user): bool
    {
        return false;
    }
}
