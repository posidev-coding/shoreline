<?php

namespace App\Support\OneTimePasswords;

use App\Models\OneTimePassword;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Spatie\OneTimePasswords\Actions\CreateOneTimePasswordAction;
use Spatie\OneTimePasswords\Models\OneTimePassword as BaseOneTimePassword;

/**
 * Stores an HMAC of the new code instead of the code, and hands the code back on the instance.
 */
class CreateHashedOneTimePassword extends CreateOneTimePasswordAction
{
    protected function createNewOneTimePassword(?int $expiresInMinutes, Authenticatable $user, ?Request $request): BaseOneTimePassword
    {
        $code = $this->passwordGenerator->generate();

        /** @var OneTimePassword $oneTimePassword */
        $oneTimePassword = $user->oneTimePasswords()->create([
            'password' => OneTimePassword::hashCode($code),
            'expires_at' => Carbon::now()->addMinutes($expiresInMinutes ?? (int) config('one-time-passwords.default_expires_in_minutes')),
            'origin_properties' => $this->originEnforcer->gatherProperties($request ?? request()),
        ]);

        $oneTimePassword->code = $code;

        return $oneTimePassword;
    }
}
