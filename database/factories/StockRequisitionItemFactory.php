<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockRequisition;
use App\Models\StockRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockRequisitionItem> */
class StockRequisitionItemFactory extends Factory
{
    protected $model = StockRequisitionItem::class;

    public function definition(): array
    {
        return [
            'stock_requisition_id' => StockRequisition::factory(),
            'item_id' => Item::factory(),
            'quantity_requested' => fake()->numberBetween(1, 20),
            'quantity_approved' => null,
        ];
    }
}
