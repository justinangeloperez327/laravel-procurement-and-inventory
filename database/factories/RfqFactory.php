<?php

namespace Database\Factories;

use App\Models\ProcurementProject;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rfq> */
class RfqFactory extends Factory
{
    protected $model = Rfq::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'reference_no' => 'RFQ-'.fake()->unique()->numerify('########'),
            'issued_at' => null,
            'submission_deadline' => now()->addWeek(),
            'status' => 'draft',
            'minimum_suppliers' => null,
            'issued_by' => null,
        ];
    }
}
