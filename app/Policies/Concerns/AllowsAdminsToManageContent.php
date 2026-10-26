<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Website content is managed by super admins and the owner, the same people who can enter /admin.
 */
trait AllowsAdminsToManageContent
{
    public function viewAny(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }
}
