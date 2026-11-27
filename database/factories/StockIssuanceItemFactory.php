<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockIssuance;
use App\Models\StockIssuanceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockIssuanceItem> */
class StockIssuanceItemFactory extends Factory
{
    protected $model = StockIssuanceItem::class;

    public function definition(): array
    {
        return [
            'stock_issuance_id' => StockIssuance::factory(),
            'stock_requisition_item_id' => null,
            'item_id' => Item::factory(),
            'inventory_lot_id' => null,
            'quantity' => fake()->numberBetween(1, 20),
            'unit_cost' => null,
        ];
    }
}
