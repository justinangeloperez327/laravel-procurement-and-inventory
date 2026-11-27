<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Department;
use App\Models\StockRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockRequisition> */
class StockRequisitionFactory extends Factory
{
    protected $model = StockRequisition::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->state(['agency_id' => $attributes['agency_id']]),
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'RIS-'.fake()->unique()->numerify('########'),
            'requested_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'purpose' => null,
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
