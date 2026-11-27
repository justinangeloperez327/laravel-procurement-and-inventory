<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'purchase_order_id' => null,
            'contract_id' => null,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'invoice_date' => now(),
            'received_at' => null,
            'total_amount' => fake()->randomFloat(2, 1000, 500000),
            'status' => InvoiceStatus::Received,
            'matched_by' => null,
            'matched_at' => null,
        ];
    }
}
