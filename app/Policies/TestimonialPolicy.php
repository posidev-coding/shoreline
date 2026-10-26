<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdminsToManageContent;

class TestimonialPolicy
{
    use AllowsAdminsToManageContent;
}
