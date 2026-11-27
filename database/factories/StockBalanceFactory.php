<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockBalance> */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'item_id' => Item::factory(),
            'inventory_lot_id' => null,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ];
    }
}
