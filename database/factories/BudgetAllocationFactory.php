<?php

namespace Database\Factories;

use App\Models\AccountCode;
use App\Models\Agency;
use App\Models\BudgetAllocation;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetAllocation> */
class BudgetAllocationFactory extends Factory
{
    protected $model = BudgetAllocation::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'department_id' => null,
            'fund_source_id' => fn (array $attributes) => FundSource::factory()->state(['agency_id' => $attributes['agency_id']]),
            'account_code_id' => AccountCode::factory(),
            'reference_no' => 'ALLOT-'.fake()->unique()->numerify('########'),
            'amount' => fake()->randomFloat(2, 100000, 5000000),
            'authorization_reference' => 'SARO-'.fake()->numerify('####'),
            'recorded_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'approved_by' => null,
            'approved_at' => null,
            'status' => 'draft',
        ];
    }
}
