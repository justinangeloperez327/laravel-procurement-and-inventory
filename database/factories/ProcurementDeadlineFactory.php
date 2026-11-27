<?php

namespace Database\Factories;

use App\Models\ProcurementDeadline;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementDeadline> */
class ProcurementDeadlineFactory extends Factory
{
    protected $model = ProcurementDeadline::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'deadline_type' => 'bid_submission',
            'due_at' => now()->addWeeks(2),
            'legal_basis' => null,
            'regulatory_rule_version_id' => null,
            'extended_from_id' => null,
            'extension_reason' => null,
            'created_by' => User::factory(),
        ];
    }
}
