<?php

namespace App\Policies;

use App\Policies\Concerns\AllowsAdmins;

/**
 * A job's photos and documents, managed on its Files tab. Clients see the shared ones through
 * the portal's project page, never through this policy.
 */
class MediaPolicy
{
    use AllowsAdmins;
}
