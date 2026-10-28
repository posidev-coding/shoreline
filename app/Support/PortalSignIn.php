<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Mail\PortalSignInCode;
use App\Models\OneTimePassword;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

/**
 * Signing in to the portal with an emailed code, or the link that carries it. Asking for a code
 * looks the same whether or not the address has a portal, so the form can't be used to find out
 * who's a client.
 */
final class PortalSignIn
{
    public const int CODE_MINUTES = 15;

    /**
     * Codes one address can ask for in ten minutes, and one network in an hour.
     */
    public const int CODES_PER_EMAIL = 3;

    public const int CODES_PER_IP = 10;

    /**
     * Codes one network can try in ten minutes, across every address.
     */
    public const int ATTEMPTS_PER_IP = 20;

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Seconds until this address or network may ask for another code, or 0 if it may now.
     */
    public static function waitBeforeSending(string $email, string $ip): int
    {
        foreach ([self::emailKey($email) => self::CODES_PER_EMAIL, self::ipKey($ip) => self::CODES_PER_IP] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return RateLimiter::availableIn($key);
            }
        }

        return 0;
    }

    /**
     * Emails a code and link to the address if it belongs to a portal user. The time it takes
     * doesn't give away which.
     */
    public static function sendCode(string $email, string $ip): void
    {
        RateLimiter::hit(self::emailKey($email), 600);
        RateLimiter::hit(self::ipKey($ip), 3600);

        (new Timebox)->call(function () use ($email): void {
            $user = self::portalUser($email);

            if ($user === null) {
                return;
            }

            /** @var OneTimePassword $oneTimePassword */
            $oneTimePassword = $user->createOneTimePassword(self::CODE_MINUTES);

            Mail::to($user)->queue(new PortalSignInCode($user, (string) $oneTimePassword->code));
        }, (int) CarbonInterval::milliseconds(300)->totalMicroseconds);
    }

    /**
     * Signs the portal user in with the code, keeping them signed in on this device. Each network
     * gets a limited number of tries across all addresses, and a wrong code takes as long to
     * refuse whether or not the address has a portal.
     */
    public static function attempt(string $email, string $code, string $ip): ConsumeOneTimePasswordResult
    {
        if (! RateLimiter::attempt(self::attemptKey($ip), self::ATTEMPTS_PER_IP, fn () => null, 600)) {
            return ConsumeOneTimePasswordResult::RateLimitExceeded;
        }

        return (new Timebox)->call(function (Timebox $timebox) use ($email, $code): ConsumeOneTimePasswordResult {
            $user = self::portalUser($email);

            if ($user === null) {
                return ConsumeOneTimePasswordResult::NoOneTimePasswordsFound;
            }

            $result = $user->attemptLoginUsingOneTimePassword($code, remember: true);

            if ($result->isOk()) {
                session()->regenerate();
                $timebox->returnEarly();
            }

            return $result;
        }, (int) CarbonInterval::milliseconds(300)->totalMicroseconds);
    }

    public static function portalUser(string $email): ?User
    {
        $user = User::query()
            ->whereRaw('lower(email) = ?', [self::normalize($email)])
            ->where('role', UserRole::Client)
            ->first();

        return $user?->canUsePortal() ? $user : null;
    }

    /**
     * What to tell someone whose code didn't work, without saying whether the address has a portal.
     */
    public static function failureMessage(ConsumeOneTimePasswordResult $result): string
    {
        return match ($result) {
            ConsumeOneTimePasswordResult::RateLimitExceeded => 'Too many tries. Wait a few minutes, then ask for a new code.',
            ConsumeOneTimePasswordResult::OneTimePasswordExpired => 'That code has expired. Ask for a new one.',
            default => 'That code didn’t work. Check it, or ask for a new one.',
        };
    }

    private static function emailKey(string $email): string
    {
        return 'portal-code:email:'.hash('sha256', self::normalize($email));
    }

    private static function ipKey(string $ip): string
    {
        return 'portal-code:ip:'.$ip;
    }

    private static function attemptKey(string $ip): string
    {
        return 'portal-attempt:ip:'.$ip;
    }
}
