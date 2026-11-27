<?php

namespace Database\Factories;

use App\Models\ProcurementLot;
use App\Models\ProcurementProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementLot> */
class ProcurementLotFactory extends Factory
{
    protected $model = ProcurementLot::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'lot_no' => fake()->unique()->numberBetween(1, 999),
            'title' => fake()->sentence(4),
            'approved_budget' => fake()->randomFloat(2, 10000, 1000000),
            'status' => 'open',
        ];
    }
}
