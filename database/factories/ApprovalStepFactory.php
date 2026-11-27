<?php

namespace Database\Factories;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalStep> */
class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'sequence' => 1,
            'name' => fake()->words(2, true),
            'permission_code' => 'purchase_request.approve',
            'department_scoped' => true,
            'is_required' => true,
        ];
    }
}
