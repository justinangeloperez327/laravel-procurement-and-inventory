<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\PhysicalCount;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PhysicalCount> */
class PhysicalCountFactory extends Factory
{
    protected $model = PhysicalCount::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'CNT-'.fake()->unique()->numerify('########'),
            'count_date' => now(),
            'status' => 'planned',
            'counted_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
