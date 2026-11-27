<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransfer> */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'reference_no' => 'TRF-'.fake()->unique()->numerify('########'),
            'from_warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'to_warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'status' => 'draft',
            'dispatched_by' => null,
            'dispatched_at' => null,
            'received_by' => null,
            'received_at' => null,
        ];
    }
}
