<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockReservation> */
class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    public function definition(): array
    {
        return [
            'stock_requisition_item_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'item_id' => Item::factory(),
            'inventory_lot_id' => null,
            'quantity' => fake()->numberBetween(1, 20),
            'status' => 'active',
            'reserved_by' => User::factory(),
            'expires_at' => null,
        ];
    }
}
