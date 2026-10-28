<?php

namespace App\Models;

use App\Enums\ClientUserRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A person's access to one client's portal, with their role there.
 *
 * @property ClientUserRole $client_role
 * @property Carbon|null $invited_at
 */
class ClientUser extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'client_role' => ClientUserRole::class,
            'invited_at' => 'datetime',
        ];
    }
}
