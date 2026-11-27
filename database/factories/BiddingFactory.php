<?php

namespace Database\Factories;

use App\Models\Bidding;
use App\Models\ProcurementProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bidding> */
class BiddingFactory extends Factory
{
    protected $model = Bidding::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'reference_no' => 'BID-'.fake()->unique()->numerify('########'),
            'bid_submission_deadline' => now()->addWeeks(3),
            'bid_opening_at' => now()->addWeeks(3)->addHour(),
            'status' => 'draft',
            'bidding_documents_fee' => null,
            'issued_by' => User::factory(),
        ];
    }
}
