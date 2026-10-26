<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdminsToManageContent;

class ServicePolicy
{
    use AllowsAdminsToManageContent;
}
