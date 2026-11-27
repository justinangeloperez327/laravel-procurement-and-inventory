<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\ProcurementProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationCriterion> */
class EvaluationCriterionFactory extends Factory
{
    protected $model = EvaluationCriterion::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'sequence' => 1,
            'criterion' => fake()->words(3, true),
            'description' => null,
            'criterion_type' => 'pass_fail',
            'weight' => null,
            'is_published' => false,
            'published_at' => null,
            'locked_at' => null,
        ];
    }
}
