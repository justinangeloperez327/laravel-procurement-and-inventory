<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetAssignment> */
class AssetAssignmentFactory extends Factory
{
    protected $model = AssetAssignment::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'custodian_id' => User::factory(),
            'department_id' => null,
            'document_type' => 'PAR',
            'document_reference' => 'PAR-'.fake()->numerify('######'),
            'assigned_at' => now(),
            'acknowledged_at' => null,
            'returned_at' => null,
            'return_condition' => null,
            'assigned_by' => User::factory(),
        ];
    }
}
