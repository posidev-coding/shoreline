<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

class ProjectPolicy
{
    use AllowsAdmins;
}
