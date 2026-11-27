<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\StockIssuance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockIssuance> */
class StockIssuanceFactory extends Factory
{
    protected $model = StockIssuance::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'stock_requisition_id' => null,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'ISS-'.fake()->unique()->numerify('########'),
            'issued_to' => null,
            'issued_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'issued_at' => null,
            'acknowledged_at' => null,
            'status' => 'draft',
            'idempotency_key' => null,
        ];
    }
}
