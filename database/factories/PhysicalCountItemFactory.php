<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PhysicalCountItem> */
class PhysicalCountItemFactory extends Factory
{
    protected $model = PhysicalCountItem::class;

    public function definition(): array
    {
        return [
            'physical_count_id' => PhysicalCount::factory(),
            'item_id' => Item::factory(),
            'inventory_lot_id' => null,
            'system_quantity' => fake()->numberBetween(0, 100),
            'counted_quantity' => null,
            'variance_reason' => null,
            'adjustment_movement_id' => null,
        ];
    }
}
