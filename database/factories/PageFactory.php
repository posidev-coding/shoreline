<?php

namespace Database\Factories;

use App\Enums\PageKey;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(PageKey::cases());

        return [
            'key' => $key,
            'title' => $key->getLabel(),
            'intro' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    public function forKey(PageKey $key): static
    {
        return $this->state(fn (array $attributes) => ['key' => $key, 'title' => $key->getLabel()]);
    }
}
