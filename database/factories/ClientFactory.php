<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'display_name' => "{$first} {$last} ".fake()->unique()->numerify('###'),
            'first_name' => $first,
            'last_name' => $last,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1252555'.fake()->unique()->numerify('####'),
            'billing_street' => fake()->streetAddress(),
            'billing_city' => 'Morehead City',
            'billing_state' => 'NC',
            'billing_zip' => '28557',
        ];
    }

    public function propertyManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'company' => 'Crystal Coast Rentals',
            'is_property_manager' => true,
        ]);
    }
}
