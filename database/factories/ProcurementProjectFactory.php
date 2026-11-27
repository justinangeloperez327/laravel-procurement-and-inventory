<?php

namespace Database\Factories;

use App\Enums\ProcurementCategory;
use App\Enums\ProcurementProjectStatus;
use App\Models\Agency;
use App\Models\FiscalYear;
use App\Models\ProcurementProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementProject> */
class ProcurementProjectFactory extends Factory
{
    protected $model = ProcurementProject::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'app_item_id' => null,
            'reference_no' => 'PB-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'description' => null,
            'category' => ProcurementCategory::Goods,
            'procurement_mode_id' => null,
            'approved_budget' => fake()->randomFloat(2, 50000, 5000000),
            'status' => ProcurementProjectStatus::Proposed,
            'end_user_department_id' => null,
            'procurement_officer_id' => null,
            'legal_regime' => 'RA12009',
        ];
    }
}
