<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Project;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state: a two-hour window tomorrow at 8 AM in the business's zone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now(config('app.business_timezone'))->addDay()->setTime(8, 0);

        return [
            'project_id' => Project::factory(),
            'type' => VisitType::Install,
            'status' => VisitStatus::Tentative,
            'starts_at' => $starts->clone()->utc(),
            'ends_at' => $starts->clone()->addHours(2)->utc(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => VisitStatus::Confirmed]);
    }
}
