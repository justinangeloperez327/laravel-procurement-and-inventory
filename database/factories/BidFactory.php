<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\Bidding;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bid> */
class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        return [
            'bidding_id' => Bidding::factory(),
            'supplier_id' => Supplier::factory(),
            'procurement_lot_id' => null,
            'submitted_at' => now(),
            'receipt_reference' => 'RCPT-'.fake()->unique()->numerify('########'),
            'bid_amount' => null,
            'is_late' => false,
            'opened_at' => null,
            'opened_by' => null,
            'status' => 'submitted',
        ];
    }
}
