<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Dispute;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dispute> */
class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'contract_id' => null,
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'dispute_type' => 'invoice',
            'description' => fake()->sentence(),
            'filed_at' => now(),
            'status' => 'open',
            'resolution' => null,
            'resolved_at' => null,
        ];
    }
}
