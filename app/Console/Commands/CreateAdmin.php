<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates an /admin account without putting a password on the command line (Cloud keeps
 * command history): the account gets an unknown random password, and the person gets
 * Filament's set-a-password email. MFA setup is required on their first sign-in.
 */
#[Signature('app:create-admin
    {email : Who to create}
    {--name= : Their name (defaults to the part of the email before the @)}
    {--owner : Create the business owner instead of a super admin}')]
#[Description('Create a super admin or owner account and email them a link to set their password')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $argument = $this->argument('email');
        $email = mb_strtolower(trim(is_string($argument) ? $argument : ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("“{$email}” isn’t a valid email address.");

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("{$email} already has an account. They can use “Forgot password?” on the /admin sign-in page.");

            return self::FAILURE;
        }

        $role = $this->option('owner') ? UserRole::Owner : UserRole::SuperAdmin;
        $user = new User([
            'name' => is_string($this->option('name')) && $this->option('name') !== '' ? $this->option('name') : Str::before($email, '@'),
            'email' => $email,
            'password' => Str::password(64),
        ]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        Filament::setCurrentPanel('admin');
        $token = Password::broker(Filament::getAuthPasswordBroker())->createToken($user);
        $notification = new ResetPassword($token);
        $notification->url = Filament::getResetPasswordUrl($token, $user);
        $user->notify($notification);

        $this->info("Created {$role->getLabel()} {$email} and emailed a link to set a password. The link works for ".config('auth.passwords.users.expire').' minutes; after that, use “Forgot password?” on the sign-in page.');

        return self::SUCCESS;
    }
}
