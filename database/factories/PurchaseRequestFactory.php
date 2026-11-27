<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\Agency;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequest> */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->state(['agency_id' => $attributes['agency_id']]),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->state(['agency_id' => $attributes['agency_id']]),
            'reference_no' => 'PR-'.fake()->unique()->numerify('########'),
            'purpose' => fake()->sentence(),
            'requested_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'status' => PurchaseRequestStatus::Draft,
            'total_amount' => 0,
            'app_item_id' => null,
            'fund_source_id' => null,
            'account_code_id' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
