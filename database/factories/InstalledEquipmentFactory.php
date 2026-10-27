<?php

namespace Database\Factories;

use App\Enums\EquipmentType;
use App\Models\InstalledEquipment;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstalledEquipment>
 */
class InstalledEquipmentFactory extends Factory
{
    /**
     * Define the model's default state: a door.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'type' => EquipmentType::Door,
            'manufacturer' => 'Clopay',
            'model' => '4050',
            'size' => '16x7',
            'installed_on' => now()->subYears(3)->toDateString(),
        ];
    }

    /**
     * A piece that hangs on a door, like its opener or springs.
     */
    public function on(InstalledEquipment $door, EquipmentType $type = EquipmentType::Opener): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $door->property_id,
            'parent_id' => $door->getKey(),
            'type' => $type,
            'manufacturer' => $type === EquipmentType::Opener ? 'LiftMaster' : null,
            'model' => $type === EquipmentType::Opener ? '8500' : null,
            'size' => null,
        ]);
    }
}
