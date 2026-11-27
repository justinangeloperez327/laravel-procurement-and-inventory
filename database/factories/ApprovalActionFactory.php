<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Models\Agency;
use App\Models\ApprovalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalAction> */
class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    public function definition(): array
    {
        return [
            'approval_step_id' => null,
            'approvable_type' => (new Agency)->getMorphClass(),
            'approvable_id' => Agency::factory(),
            'actor_id' => User::factory(),
            'on_behalf_of_id' => null,
            'delegation_id' => null,
            'decision' => ApprovalDecision::Approved,
            'remarks' => null,
            'acted_at' => now(),
        ];
    }
}
