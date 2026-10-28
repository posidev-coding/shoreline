<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMessage>
 */
class ProjectMessageFactory extends Factory
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
            'is_from_client' => false,
            'body' => fake()->sentence(),
        ];
    }

    public function fromClient(): static
    {
        return $this->state(fn (array $attributes) => ['is_from_client' => true]);
    }
}
