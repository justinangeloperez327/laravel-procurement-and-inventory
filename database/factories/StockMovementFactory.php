<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Agency;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'warehouse_location_id' => null,
            'item_id' => fn (array $attributes) => Item::factory()->state(['agency_id' => $attributes['agency_id']]),
            'inventory_lot_id' => null,
            'movement_type' => StockMovementType::AdjustmentPlus,
            'quantity' => fake()->numberBetween(1, 100),
            'unit_cost' => null,
            'source_type' => null,
            'source_id' => null,
            'inspection_item_id' => null,
            'reverses_movement_id' => null,
            'idempotency_key' => fake()->uuid(),
            'posted_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'posted_at' => now(),
            'remarks' => null,
        ];
    }
}
