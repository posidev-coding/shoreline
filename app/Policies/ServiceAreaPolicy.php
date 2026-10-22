<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdminsToManageContent;

class ServiceAreaPolicy
{
    use AllowsAdminsToManageContent;
}
