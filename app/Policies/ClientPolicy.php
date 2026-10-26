<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

class ClientPolicy
{
    use AllowsAdmins;
}
