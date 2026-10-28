<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\AllowsAdmins;
use Illuminate\Database\Eloquent\Model;

class ProjectPolicy
{
    use AllowsAdmins {
        view as adminsView;
    }

    /**
     * Admins see every job; a portal user, only the ones their clients' portals show.
     */
    public function view(User $user, Model $model): bool
    {
        return $this->adminsView($user, $model)
            || ($model instanceof Project && Project::query()->visibleInPortalTo($user)->whereKey($model->getKey())->exists());
    }
}
