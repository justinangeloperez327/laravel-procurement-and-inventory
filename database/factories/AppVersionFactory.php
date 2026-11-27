<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\AnnualProcurementPlan;
use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppVersion> */
class AppVersionFactory extends Factory
{
    protected $model = AppVersion::class;

    public function definition(): array
    {
        return [
            'app_id' => AnnualProcurementPlan::factory(),
            'version' => 1,
            'status' => PlanStatus::Draft,
            'is_indicative' => false,
            'total_amount' => 0,
            'prepared_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'revision_reason' => null,
            'supersedes_id' => null,
        ];
    }
}
