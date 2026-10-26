<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsAdmins;
use Illuminate\Database\Eloquent\Model;

/**
 * Pages are a fixed set, each with its own route: they can be edited, not created or deleted.
 */
class PagePolicy
{
    use AllowsAdmins;

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
