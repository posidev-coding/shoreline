<?php

namespace Database\Factories;

use App\Enums\ClientUserRole;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
        ]);
    }

    /**
     * Has app-based MFA set up, which /admin requires before anything else.
     */
    public function withMfa(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => (new Google2FA)->generateSecretKey(),
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }

    /**
     * Someone who signs in to the client's portal with an emailed code, so has no password.
     */
    public function portalUserOf(Client $client, ClientUserRole $role = ClientUserRole::Primary): static
    {
        return $this->state(fn (array $attributes) => ['password' => null])
            ->afterCreating(fn (User $user) => $client->portalUsers()->attach($user->getKey(), ['client_role' => $role->value]));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
