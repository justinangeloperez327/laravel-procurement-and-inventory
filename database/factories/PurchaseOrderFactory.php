<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Agency;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'contract_id' => null,
            'contract_award_id' => null,
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'PO-'.fake()->unique()->numerify('########'),
            'issued_on' => null,
            'delivery_due_on' => null,
            'delivery_place' => null,
            'total_amount' => fake()->randomFloat(2, 1000, 500000),
            'status' => PurchaseOrderStatus::Draft,
            'acknowledged_at' => null,
        ];
    }
}
