<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'rfq_id' => Rfq::factory(),
            'supplier_id' => Supplier::factory(),
            'received_at' => now(),
            'total_amount' => fake()->randomFloat(2, 1000, 500000),
            'validity_until' => null,
            'status' => 'received',
            'is_late' => false,
        ];
    }
}
