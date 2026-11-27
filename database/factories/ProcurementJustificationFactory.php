<?php

namespace Database\Factories;

use App\Models\ProcurementJustification;
use App\Models\ProcurementModeDefinition;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementJustification> */
class ProcurementJustificationFactory extends Factory
{
    protected $model = ProcurementJustification::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_mode_id' => ProcurementModeDefinition::factory(),
            'regulatory_rule_version_id' => null,
            'justification' => fake()->paragraph(),
            'evidence' => null,
            'prepared_by' => User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision' => null,
            'decision_remarks' => null,
        ];
    }
}
