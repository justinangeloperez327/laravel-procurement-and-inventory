<?php

namespace Database\Factories;

use App\Models\AcceptanceReport;
use App\Models\Agency;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcceptanceReport> */
class AcceptanceReportFactory extends Factory
{
    protected $model = AcceptanceReport::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'inspection_id' => Inspection::factory(),
            'reference_no' => 'IAR-'.fake()->unique()->numerify('########'),
            'accepted_by' => fn (array $attributes) => User::factory()->state(['agency_id' => $attributes['agency_id']]),
            'accepted_at' => now(),
            'document_id' => null,
        ];
    }
}
