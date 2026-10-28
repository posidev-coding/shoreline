<?php

namespace App\Models;

use Spatie\OneTimePasswords\Models\OneTimePassword as BaseOneTimePassword;

/**
 * A portal sign-in code. The table holds only an HMAC of the code; the code itself is on the
 * instance that creates it, long enough to email it, and never saved.
 */
class OneTimePassword extends BaseOneTimePassword
{
    public ?string $code = null;

    /**
     * Keyed with the app key: six digits alone would take a moment to recover from a plain hash.
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
