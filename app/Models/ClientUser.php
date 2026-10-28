<?php

namespace App\Models;

use App\Enums\ClientUserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A person's access to one client's portal, with their role there.
 *
 * @property ClientUserRole $client_role
 * @property Carbon|null $invited_at
 */
class ClientUser extends Pivot
{
    public $incrementing = true;

    /**
     * The access a portal relationship (Client::portalUsers(), User::clients()) loaded onto one
     * of its rows, as `access`.
     */
    public static function of(Model $related): self
    {
        $access = $related->getRelation('access');

        if (! $access instanceof self) {
            throw new LogicException('Loaded without its portal access.');
        }

        return $access;
    }

    protected function casts(): array
    {
        return [
            'client_role' => ClientUserRole::class,
            'invited_at' => 'datetime',
        ];
    }
}
