<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdminsToManageContent;

class ShowcasePolicy
{
    use AllowsAdminsToManageContent;
}
