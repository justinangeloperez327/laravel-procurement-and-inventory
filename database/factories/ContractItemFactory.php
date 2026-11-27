<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractItem> */
class ContractItemFactory extends Factory
{
    protected $model = ContractItem::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'procurement_project_item_id' => null,
            'item_id' => null,
            'description' => fake()->words(4, true),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->randomFloat(2, 10, 5000),
            'total' => fake()->randomFloat(2, 100, 100000),
            'delivery_due_on' => null,
        ];
    }
}
