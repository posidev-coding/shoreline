<?php

namespace Database\Factories;

use App\Enums\PartCategory;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => PartCategory::Hardware,
            'name' => fake()->randomElement(['Nylon roller, 13 ball', 'Steel hinge #2', 'Bottom bracket']).' '.fake()->unique()->numerify('###'),
            'cost' => '8.0000',
            'price' => '18.0000',
        ];
    }

    /**
     * A torsion spring kept on the truck, counted in stock.
     */
    public function spring(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => PartCategory::TorsionSpring,
            'name' => 'Torsion spring 0.243 x 2 x 32, left wind '.fake()->unique()->numerify('###'),
            'specs' => ['wire_size' => '0.243', 'inside_diameter' => '2', 'length' => '32', 'wind' => 'left'],
            'cost' => '42.0000',
            'price' => '89.0000',
            'warranty_months' => 36,
            'track_stock' => true,
            'reorder_point' => '2.00',
        ]);
    }

    public function opener(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => PartCategory::Opener,
            'name' => 'LiftMaster 87504 wall-mount opener',
            'manufacturer' => 'LiftMaster',
            'model' => '87504-267',
            'specs' => ['drive' => 'jackshaft', 'horsepower' => '1/2'],
            'cost' => '520.0000',
            'price' => '849.0000',
            'warranty_months' => 60,
        ]);
    }

    public function door(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => PartCategory::Door,
            'name' => 'Clopay Classic 4050, 16 x 7, wind rated',
            'manufacturer' => 'Clopay',
            'model' => '4050',
            'cost' => '1400.0000',
            'price' => '2290.0000',
            'warranty_months' => 120,
        ]);
    }

    public function tracked(): static
    {
        return $this->state(fn (array $attributes) => ['track_stock' => true]);
    }
}
