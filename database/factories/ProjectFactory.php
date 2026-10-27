<?php

namespace Database\Factories;

use App\Enums\ProjectStage;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state. The client comes from the property.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'title' => fake()->randomElement(['Replace broken torsion spring', 'New double door', 'Opener install', 'Rehang off-track door']),
            'stage' => ProjectStage::Estimating,
            'description' => fake()->sentence(),
        ];
    }

    public function stage(ProjectStage $stage): static
    {
        return $this->state(fn (array $attributes) => ['stage' => $stage]);
    }
}
