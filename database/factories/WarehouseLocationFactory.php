<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WarehouseLocation> */
class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => 'BIN-'.fake()->unique()->numerify('####'),
            'name' => null,
            'is_active' => true,
        ];
    }
}
