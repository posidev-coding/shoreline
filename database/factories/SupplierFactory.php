<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Door Supply',
            'rep_name' => fake()->name(),
            'rep_phone' => '+1919555'.fake()->numerify('####'),
            'account_number' => fake()->numerify('SH-#####'),
        ];
    }
}
