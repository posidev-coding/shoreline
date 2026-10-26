<?php

namespace Database\Factories;

use App\Enums\County;
use App\Enums\PropertyType;
use App\Models\Client;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'street' => fake()->buildingNumber().' '.fake()->streetName(),
            'city' => 'Morehead City',
            'state' => 'NC',
            'zip' => '28557',
            'county' => County::Carteret,
            'type' => PropertyType::Primary,
        ];
    }

    public function vacationRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PropertyType::VacationRental,
            'access_info' => 'Lockbox 4821 on the side door',
            'turnover_notes' => 'Saturday changeover, 10 AM to 4 PM',
        ]);
    }
}
