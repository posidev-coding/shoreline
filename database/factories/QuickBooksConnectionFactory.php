<?php

namespace Database\Factories;

use App\Enums\QuickBooksEnvironment;
use App\Models\QuickBooksConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickBooksConnection>
 */
class QuickBooksConnectionFactory extends Factory
{
    /**
     * A connected sandbox company with an hour-fresh access token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment' => QuickBooksEnvironment::Sandbox,
            'realm_id' => (string) fake()->unique()->numberBetween(1_000_000_000, 9_999_999_999),
            'company_name' => 'Sandbox Company_US_1',
            'access_token' => 'access-'.fake()->sha1(),
            'refresh_token' => 'refresh-'.fake()->sha1(),
            'access_expires_at' => now()->addHour(),
            'refresh_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now(),
            'connected_at' => now(),
        ];
    }

    public function expiring(): static
    {
        return $this->state(fn (array $attributes) => ['access_expires_at' => now()->addMinute()]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_token' => null,
            'refresh_token' => null,
            'access_expires_at' => null,
            'disconnected_at' => now(),
            'disconnect_reason' => 'Disconnected by admin@example.com',
        ]);
    }
}
