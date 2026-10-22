<?php

namespace Database\Factories;

use App\Models\Showcase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Showcase>
 */
class ShowcaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3), '.'));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'story' => '<p>'.fake()->paragraph().'</p>',
            'has_photo_release' => true,
            'is_featured' => false,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => null]);
    }

    public function withoutPhotoRelease(): static
    {
        return $this->state(fn (array $attributes) => ['has_photo_release' => false]);
    }
}
