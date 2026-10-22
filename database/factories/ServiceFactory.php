<?php

namespace Database\Factories;

use App\Enums\ServiceIcon;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(2), '.'));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement(ServiceIcon::cases()),
            'headline' => $name.' on the Crystal Coast',
            'summary' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
