<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

/**
 * The admin's side of a job's thread. Portal users post through App\Support\MessageThread,
 * which checks their access and role itself.
 */
class ProjectMessagePolicy
{
    use AllowsAdmins;
}
