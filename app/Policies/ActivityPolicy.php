<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The full activity log is for super admins, who support the site. The owner sees each
 * record's own history from its page. Nobody edits or deletes the log here.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
