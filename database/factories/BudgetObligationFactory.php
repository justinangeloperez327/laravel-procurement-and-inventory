<?php

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\BudgetObligation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetObligation> */
class BudgetObligationFactory extends Factory
{
    protected $model = BudgetObligation::class;

    public function definition(): array
    {
        return [
            'budget_allocation_id' => BudgetAllocation::factory(),
            'budget_reservation_id' => null,
            'contract_id' => null,
            'purchase_order_id' => null,
            'ors_burs_reference' => null,
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'obligated_by' => User::factory(),
            'obligated_at' => now(),
            'status' => 'active',
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
