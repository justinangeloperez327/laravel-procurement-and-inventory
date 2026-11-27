<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Agency;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\Ppmp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ppmp> */
class PpmpFactory extends Factory
{
    protected $model = Ppmp::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->state(['agency_id' => $attributes['agency_id']]),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'PPMP-'.fake()->unique()->numerify('########'),
            'version' => 1,
            'parent_ppmp_id' => null,
            'status' => PlanStatus::Draft,
            'total_amount' => 0,
            'prepared_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'remarks' => null,
        ];
    }
}
