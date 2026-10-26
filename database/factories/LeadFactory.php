<?php

namespace Database\Factories;

use App\Enums\ContactMethod;
use App\Enums\LeadStatus;
use App\Enums\Urgency;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => LeadStatus::New,
            'urgency' => Urgency::Soon,
            'answers' => ['door_size' => 'double', 'symptoms' => ['noisy']],
            'message' => fake()->sentence(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1252555'.fake()->unique()->numerify('####'),
            'preferred_contact' => ContactMethod::Call,
            'street' => fake()->streetAddress(),
            'town' => 'Morehead City',
            'zip' => '28557',
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeadStatus::Contacted, 'contacted_at' => now()]);
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeadStatus::Spam]);
    }
}
