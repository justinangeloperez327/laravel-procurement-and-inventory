<?php

namespace Database\Factories;

use App\Models\PostQualification;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostQualification> */
class PostQualificationFactory extends Factory
{
    protected $model = PostQualification::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'supplier_id' => Supplier::factory(),
            'bid_id' => null,
            'quotation_id' => null,
            'started_at' => null,
            'completed_at' => null,
            'result' => 'pending',
            'findings' => null,
            'report_document_id' => null,
        ];
    }
}
