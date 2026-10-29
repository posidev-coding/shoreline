<?php

namespace Database\Factories;

use App\Models\QuickBooksCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickBooksCustomer>
 */
class QuickBooksCustomerFactory extends Factory
{
    /**
     * An active, top-level customer in the factory connection's company.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'realm_id' => '9130350000000001',
            'qb_id' => (string) fake()->unique()->numberBetween(1, 99_999),
            'qb_sync_token' => '0',
            'qb_payload' => [],
            'display_name' => "{$first} {$last}",
            'given_name' => $first,
            'family_name' => $last,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1252555'.fake()->unique()->numerify('####'),
            'billing_city' => 'Beaufort',
            'is_active' => true,
        ];
    }
}
