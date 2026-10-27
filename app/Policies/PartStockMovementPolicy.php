<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsAdmins;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock movements are a ledger, written only by the stock actions: they can be read, never
 * created, edited, or deleted by hand.
 */
class PartStockMovementPolicy
{
    use AllowsAdmins;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
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
