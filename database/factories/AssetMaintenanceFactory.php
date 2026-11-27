<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssetMaintenance> */
class AssetMaintenanceFactory extends Factory
{
    protected $model = AssetMaintenance::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'maintenance_type' => 'preventive',
            'performed_on' => now(),
            'performed_by' => null,
            'cost' => null,
            'description' => fake()->sentence(),
            'next_due_on' => null,
        ];
    }
}
