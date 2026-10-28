<?php

namespace App\Support;

use App\Enums\ClientUserRole;
use App\Enums\UserRole;
use App\Mail\PortalInvitation;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Who can sign in to a client's portal. Inviting makes the person a user if they aren't one,
 * gives them access to this client, and emails them how to sign in. Removing the last access
 * a person has deletes them, since a portal user is nothing without one.
 */
final class PortalAccess
{
    /**
     * The admin an address belongs to, if any: admins never become portal users.
     */
    public static function adminWithEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->where('role', '!=', UserRole::Client)
            ->first();
    }

    public static function hasAccess(Client $client, string $email): bool
    {
        return $client->portalUsers()->whereRaw('lower(users.email) = ?', [mb_strtolower(trim($email))])->exists();
    }

    public static function invite(Client $client, string $name, string $email, ClientUserRole $role): User
    {
        if (self::adminWithEmail($email) !== null) {
            throw new InvalidArgumentException('An admin can’t be a portal user.');
        }

        $user = DB::transaction(function () use ($client, $name, $email, $role): User {
            // Someone already in another client's portal keeps their name; they're the same person.
            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first()
                ?? User::query()->create(['name' => $name, 'email' => $email]);

            $client->portalUsers()->syncWithoutDetaching([$user->getKey() => ['client_role' => $role->value, 'invited_at' => now()]]);

            activity()
                ->performedOn($client)
                ->event('portal')
                ->log("Invited {$user->email} to the portal as {$role->getLabel()}");

            return $user;
        });

        Mail::to($user)->queue(new PortalInvitation($client, $user));

        return $user;
    }

    public static function resendInvitation(Client $client, User $user): void
    {
        $client->portalUsers()->updateExistingPivot($user->getKey(), ['invited_at' => now()]);

        Mail::to($user)->queue(new PortalInvitation($client, $user));
    }

    public static function changeRole(Client $client, User $user, ClientUserRole $role): void
    {
        $client->portalUsers()->updateExistingPivot($user->getKey(), ['client_role' => $role->value]);

        activity()
            ->performedOn($client)
            ->event('portal')
            ->log("Made {$user->email} {$role->getLabel()} in the portal");
    }

    public static function remove(Client $client, User $user): void
    {
        DB::transaction(function () use ($client, $user): void {
            $client->portalUsers()->detach($user->getKey());

            activity()
                ->performedOn($client)
                ->event('portal')
                ->log("Removed {$user->email} from the portal");

            if ($user->role === UserRole::Client && ! $user->clients()->withTrashed()->exists()) {
                $user->deleteAllOneTimePasswords();
                $user->delete();
            }
        });
    }
}
