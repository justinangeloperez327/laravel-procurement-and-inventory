<?php

namespace Database\Factories;

use App\Models\BudgetDisbursement;
use App\Models\BudgetObligation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetDisbursement> */
class BudgetDisbursementFactory extends Factory
{
    protected $model = BudgetDisbursement::class;

    public function definition(): array
    {
        return [
            'budget_obligation_id' => BudgetObligation::factory(),
            'payment_id' => null,
            'amount' => fake()->randomFloat(2, 100, 10000),
            'disbursed_at' => now(),
            'recorded_by' => User::factory(),
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
