<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
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
            'txn_date' => now()->toDateString(),
            'total_amt' => '450.00',
        ];
    }
}
