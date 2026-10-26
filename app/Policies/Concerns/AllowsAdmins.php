<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Super admins and the owner, the same people who can enter /admin, manage the website and
 * the business records. Clients reach their own records through the portal's policies (M4).
 */
trait AllowsAdmins
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

    public function restore(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }
}
