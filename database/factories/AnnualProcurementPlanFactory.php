<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Agency;
use App\Models\AnnualProcurementPlan;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnnualProcurementPlan> */
class AnnualProcurementPlanFactory extends Factory
{
    protected $model = AnnualProcurementPlan::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'APP-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'status' => PlanStatus::Draft,
        ];
    }
}
