<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QuickBooksSyncIssue;
use App\Models\User;

/**
 * Sync issues are for super admins, who look after the QuickBooks connection. Only the sync
 * raises them; a super admin retries or resolves them.
 */
class QuickBooksSyncIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, QuickBooksSyncIssue $issue): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function update(User $user, QuickBooksSyncIssue $issue): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
