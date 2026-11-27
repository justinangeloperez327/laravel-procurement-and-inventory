<?php

namespace Database\Factories;

use App\Models\BudgetAllocation;
use App\Models\BudgetReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetReservation> */
class BudgetReservationFactory extends Factory
{
    protected $model = BudgetReservation::class;

    public function definition(): array
    {
        return [
            'budget_allocation_id' => BudgetAllocation::factory(),
            'purchase_request_id' => null,
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'status' => 'active',
            'reserved_by' => User::factory(),
            'reserved_at' => now(),
            'released_at' => null,
            'idempotency_key' => fake()->uuid(),
        ];
    }
}
