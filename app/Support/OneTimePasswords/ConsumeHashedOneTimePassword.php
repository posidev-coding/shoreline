<?php

namespace App\Support\OneTimePasswords;

use App\Models\OneTimePassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\OneTimePasswords\Actions\ConsumeOneTimePasswordAction;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

/**
 * Compares the HMAC of the code typed or linked, since that's all the table holds.
 */
class ConsumeHashedOneTimePassword extends ConsumeOneTimePasswordAction
{
    public function execute(Authenticatable $user, string $password, Request $request): ConsumeOneTimePasswordResult
    {
        return parent::execute($user, OneTimePassword::hashCode(trim($password)), $request);
    }
}
