<?php

namespace Database\Factories;

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    /**
     * A pending estimate in the factory connection's company, for a customer no client is linked to.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = (string) fake()->numberBetween(1, 999);

        return [
            'realm_id' => '9130350000000001',
            'qb_id' => (string) fake()->unique()->numberBetween(1, 99_999),
            'qb_sync_token' => '0',
            'qb_payload' => [],
            'customer_qb_id' => $customer,
            'client_qb_id' => $customer,
            'customer_name' => fake()->name(),
            'doc_number' => (string) fake()->unique()->numberBetween(1001, 9999),
            'txn_date' => now()->subDays(3)->toDateString(),
            'status' => EstimateStatus::Pending,
            'total_amt' => '450.00',
        ];
    }
}
