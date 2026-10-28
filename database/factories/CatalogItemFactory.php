<?php

namespace Database\Factories;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    /**
     * An active Service item.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' service';

        return [
            'realm_id' => '9130350000000001',
            'qb_id' => (string) fake()->unique()->numberBetween(1, 99_999),
            'qb_sync_token' => '0',
            'qb_payload' => [],
            'name' => $name,
            'fully_qualified_name' => $name,
            'type' => CatalogItemType::Service,
            'unit_price' => '95.0000',
            'is_taxable' => false,
            'is_active' => true,
        ];
    }

    public function nonInventory(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CatalogItemType::NonInventory, 'is_taxable' => true]);
    }
}
