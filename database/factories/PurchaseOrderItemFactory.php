<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderItem> */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'contract_item_id' => null,
            'item_id' => null,
            'description' => fake()->words(4, true),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->randomFloat(2, 10, 5000),
            'total' => fake()->randomFloat(2, 100, 100000),
        ];
    }
}
