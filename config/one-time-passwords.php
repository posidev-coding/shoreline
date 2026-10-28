<?php

use App\Models\OneTimePassword;
use App\Support\OneTimePasswords\ConsumeHashedOneTimePassword;
use App\Support\OneTimePasswords\CreateHashedOneTimePassword;
use Spatie\OneTimePasswords\Notifications\OneTimePasswordNotification;
use Spatie\OneTimePasswords\Support\OriginInspector\DoNotEnforceOrigin;
use Spatie\OneTimePasswords\Support\PasswordGenerators\NumericOneTimePasswordGenerator;

return [
    /*
     * one-time passwords should be consumed within this number of minutes
     */
    'default_expires_in_minutes' => 15,

    /*
     * When this setting is active, we'll delete all previous one-time passwords for
     * a user when generating a new one
     */
    'only_one_active_one_time_password_per_user' => true,

    /*
     * When this option is active, we'll try to ensure that the one-time password can only
     * be consumed on the platform where it was requested on
     */
    'enforce_same_origin' => false,

    /*
     * This class is responsible to enforce that the one-time password can only be consumed on
     * the platform it was requested on.
     *
     * If you do not wish to enforce this, set this value to
     * Spatie\OneTimePasswords\Support\OriginInspector\DoNotEnforceOrigin
     */
    // Off for the portal: its emailed link is often opened in a mail app's browser or on another
    // device, which the default would refuse for a different IP or user agent.
    'origin_enforcer' => DoNotEnforceOrigin::class,

    /*
     * This class generates a random password
     */
    'password_generator' => NumericOneTimePasswordGenerator::class,

    /*
     * By default, the password generator will create a password with
     * this number of digits
     */
    'password_length' => 6,

    /*
     * The Livewire component will redirect successfully authenticated users
     * to this URL.
     */
    'redirect_successful_authentication_to' => '/portal',

    /*
     * These values are used to rate limit the number of attempts
     * that may be made to consume a one-time password.
     */
    'rate_limit_attempts' => [
        // Five wrong codes per user in ten minutes, so guessing six digits stays hopeless.
        'max_attempts_per_user' => 5,
        'time_window_in_seconds' => 600,
    ],

    /*
     * The model uses to store one-time passwords
     */
    'model' => OneTimePassword::class,

    /*
     * The notification used to send a one-time password to a user
     */
    'notification' => OneTimePasswordNotification::class,

    /*
     * These class are responsible for performing core tasks regarding one-time passwords.
     * You can customize them by creating a class that extends the default, and
     * by specifying your custom class name here.
     */
    'actions' => [
        // Store and compare an HMAC of each code, never the code itself.
        'create_one_time_password' => CreateHashedOneTimePassword::class,
        'consume_one_time_password' => ConsumeHashedOneTimePassword::class,
    ],
];
