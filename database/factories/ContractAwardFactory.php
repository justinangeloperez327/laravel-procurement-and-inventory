<?php

namespace Database\Factories;

use App\Models\ContractAward;
use App\Models\ProcurementProject;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractAward> */
class ContractAwardFactory extends Factory
{
    protected $model = ContractAward::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'supplier_id' => Supplier::factory(),
            'bac_resolution_id' => null,
            'awarded_bid_id' => null,
            'awarded_quotation_id' => null,
            'awarded_amount' => fake()->randomFloat(2, 10000, 1000000),
            'approved_by' => null,
            'approved_at' => null,
            'noa_reference' => null,
            'notice_of_award_at' => null,
            'status' => 'pending_approval',
        ];
    }
}
