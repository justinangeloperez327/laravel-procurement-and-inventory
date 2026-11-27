<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPerformance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierPerformance> */
class SupplierPerformanceFactory extends Factory
{
    protected $model = SupplierPerformance::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'contract_id' => null,
            'evaluated_by' => User::factory(),
            'period_start' => null,
            'period_end' => null,
            'rating' => fake()->randomFloat(2, 1, 5),
            'remarks' => null,
        ];
    }
}
