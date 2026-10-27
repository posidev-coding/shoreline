<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Part;
use App\Models\PartStockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * For reading tests only. Real movements go through App\Support\Stock, which keeps on_hand in step.
 *
 * @extends Factory<PartStockMovement>
 */
class PartStockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part_id' => Part::factory(),
            'type' => StockMovementType::Received,
            'quantity' => '1.00',
            'balance_after' => '1.00',
        ];
    }
}
