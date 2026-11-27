<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\ComplianceFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ComplianceFlag> */
class ComplianceFlagFactory extends Factory
{
    protected $model = ComplianceFlag::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'subject_type' => (new Agency)->getMorphClass(),
            'subject_id' => Agency::factory(),
            'flag_type' => 'possible_splitting',
            'severity' => 'warning',
            'description' => fake()->sentence(),
            'raised_by' => null,
            'raised_at' => now(),
            'status' => 'open',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }
}
