<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectLineItem>
 */
class ProjectLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'description' => fake()->randomElement(['Torsion spring, 0.243 x 2 x 32', 'Labor', 'Trip charge', 'Bottom weather seal']),
            'quantity' => 1,
            'unit_price' => fake()->randomFloat(2, 20, 400),
            'is_taxable' => true,
        ];
    }
}
