<?php

namespace Database\Factories;

use App\Models\ProcurementModeDefinition;
use App\Models\ProcurementModeRule;
use App\Models\RegulatoryRuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementModeRule> */
class ProcurementModeRuleFactory extends Factory
{
    protected $model = ProcurementModeRule::class;

    public function definition(): array
    {
        return [
            'procurement_mode_id' => ProcurementModeDefinition::factory(),
            'regulatory_rule_version_id' => RegulatoryRuleVersion::factory(),
            'rule_type' => 'eligibility',
            'is_blocking' => true,
        ];
    }
}
