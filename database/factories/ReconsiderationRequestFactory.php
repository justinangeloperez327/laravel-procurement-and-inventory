<?php

namespace Database\Factories;

use App\Models\ProcurementProject;
use App\Models\ReconsiderationRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReconsiderationRequest> */
class ReconsiderationRequestFactory extends Factory
{
    protected $model = ReconsiderationRequest::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'supplier_id' => Supplier::factory(),
            'bac_resolution_id' => null,
            'filed_at' => now(),
            'grounds' => fake()->paragraph(),
            'status' => 'filed',
            'decided_at' => null,
            'decision' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
