<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Agency;
use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Delivery> */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'purchase_order_id' => fn (array $attributes) => PurchaseOrder::factory()->state(['agency_id' => $attributes['agency_id']]),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'warehouse_id' => null,
            'reference_no' => 'DR-'.fake()->unique()->numerify('########'),
            'delivered_at' => now(),
            'received_by' => null,
            'received_at' => null,
            'status' => DeliveryStatus::Scheduled,
        ];
    }
}
