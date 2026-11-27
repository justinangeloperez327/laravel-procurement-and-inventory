<?php

namespace Database\Factories;

use App\Models\InventoryLot;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryLot> */
class InventoryLotFactory extends Factory
{
    protected $model = InventoryLot::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'lot_number' => 'LOT-'.fake()->unique()->numerify('######'),
            'expiry_date' => null,
            'manufactured_on' => null,
        ];
    }
}
