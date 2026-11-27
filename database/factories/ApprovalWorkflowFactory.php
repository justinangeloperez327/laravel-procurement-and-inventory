<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalWorkflow> */
class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => 'WF-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'subject_type' => 'purchase_request',
            'is_active' => true,
            'effective_from' => null,
        ];
    }
}
