<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Agency;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\MarketScoping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketScoping> */
class MarketScopingFactory extends Factory
{
    protected $model = MarketScoping::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->state(['agency_id' => $attributes['agency_id']]),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'MS-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'description' => null,
            'conducted_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'conducted_on' => now(),
            'status' => PlanStatus::Draft,
        ];
    }
}
