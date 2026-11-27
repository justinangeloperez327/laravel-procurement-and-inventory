<?php

namespace Database\Factories;

use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PpmpItem> */
class PpmpItemFactory extends Factory
{
    protected $model = PpmpItem::class;

    public function definition(): array
    {
        return [
            'ppmp_id' => Ppmp::factory(),
            'item_id' => null,
            'market_scoping_item_id' => null,
            'description' => fake()->words(4, true),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 100),
            'estimated_unit_cost' => fake()->randomFloat(2, 10, 5000),
            'proposed_mode' => null,
            'schedule_start' => null,
            'schedule_end' => null,
            'account_code_id' => null,
            'fund_source_id' => null,
            'remarks' => null,
        ];
    }
}
