<?php

namespace Database\Factories;

use App\Enums\RuleStatus;
use App\Models\RegulatoryRuleVersion;
use App\Models\RegulatorySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegulatoryRuleVersion> */
class RegulatoryRuleVersionFactory extends Factory
{
    protected $model = RegulatoryRuleVersion::class;

    public function definition(): array
    {
        return [
            'regulatory_source_id' => RegulatorySource::factory(),
            'rule_code' => 'RULE-'.fake()->unique()->numerify('#####'),
            'version' => 1,
            'provision' => fake()->words(3, true),
            'legal_reference' => null,
            'entity_type' => null,
            'category' => null,
            'conditions' => null,
            'exceptions' => null,
            'approved_interpretation' => null,
            'effective_from' => now()->startOfYear(),
            'effective_to' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'test_reference' => null,
            'status' => RuleStatus::Draft,
        ];
    }
}
