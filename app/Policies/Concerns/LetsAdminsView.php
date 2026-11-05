<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records mirrored from QuickBooks: admins see them, and nobody edits them here.
 */
trait LetsAdminsView
{
    public function viewAny(User $user): bool
    {
        return $user->role->canAccessAdmin();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->role->canAccessAdmin();
    }
}
