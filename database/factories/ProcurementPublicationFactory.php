<?php

namespace Database\Factories;

use App\Models\ProcurementProject;
use App\Models\ProcurementPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementPublication> */
class ProcurementPublicationFactory extends Factory
{
    protected $model = ProcurementPublication::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'channel' => 'philgeps',
            'reference_no' => null,
            'posted_at' => now(),
            'removed_at' => null,
            'posted_by' => User::factory(),
            'document_id' => null,
        ];
    }
}
