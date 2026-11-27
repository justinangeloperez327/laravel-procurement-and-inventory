<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'department_id' => null,
            'code' => 'WH-'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'address' => null,
            'custodian_id' => null,
            'is_active' => true,
        ];
    }
}
