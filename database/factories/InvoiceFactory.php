<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * An unpaid invoice, due in a month, in the factory connection's company.
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
            'txn_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amt' => '450.00',
            'balance' => '450.00',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['balance' => '0.00']);
    }
}
