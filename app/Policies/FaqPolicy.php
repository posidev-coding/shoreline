<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdminsToManageContent;

class FaqPolicy
{
    use AllowsAdminsToManageContent;
}
