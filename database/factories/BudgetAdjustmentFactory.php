<?php

namespace Database\Factories;

use App\Models\BudgetAdjustment;
use App\Models\BudgetAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetAdjustment> */
class BudgetAdjustmentFactory extends Factory
{
    protected $model = BudgetAdjustment::class;

    public function definition(): array
    {
        return [
            'budget_allocation_id' => BudgetAllocation::factory(),
            'adjustment_type' => 'augmentation',
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'authorization_reference' => 'ADJ-'.fake()->numerify('####'),
            'reason' => fake()->sentence(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'related_adjustment_id' => null,
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
