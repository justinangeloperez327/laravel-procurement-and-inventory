<?php

namespace Database\Factories;

use App\Models\ProcurementProject;
use App\Models\Protest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Protest> */
class ProtestFactory extends Factory
{
    protected $model = Protest::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'supplier_id' => Supplier::factory(),
            'filed_at' => now(),
            'grounds' => fake()->paragraph(),
            'protest_fee' => null,
            'status' => 'filed',
            'blocks_award' => true,
            'decided_by' => null,
            'decided_at' => null,
            'decision' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
