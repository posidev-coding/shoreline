<?php

namespace Database\Factories;

use App\Enums\ReviewSource;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote' => fake()->paragraph(),
            'author_name' => fake()->firstName().' '.fake()->randomLetter().'.',
            'rating' => 5,
            'source' => ReviewSource::Google,
            'reviewed_on' => fake()->dateTimeBetween('-1 year'),
            'is_featured' => false,
            'is_published' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
