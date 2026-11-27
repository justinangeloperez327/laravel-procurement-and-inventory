<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransferItem> */
class StockTransferItemFactory extends Factory
{
    protected $model = StockTransferItem::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'item_id' => Item::factory(),
            'inventory_lot_id' => null,
            'quantity_sent' => fake()->numberBetween(1, 20),
            'quantity_received' => null,
        ];
    }
}
