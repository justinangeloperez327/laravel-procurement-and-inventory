<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\FrameworkAgreement;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FrameworkAgreement> */
class FrameworkAgreementFactory extends Factory
{
    protected $model = FrameworkAgreement::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['agency_id' => $attributes['agency_id']]),
            'procurement_project_id' => null,
            'reference_no' => 'FA-'.fake()->unique()->numerify('########'),
            'starts_on' => now(),
            'ends_on' => now()->addYear(),
            'ceiling_amount' => null,
            'status' => 'active',
        ];
    }
}
