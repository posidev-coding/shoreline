<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

class ProjectUpdatePolicy
{
    use AllowsAdmins;
}
