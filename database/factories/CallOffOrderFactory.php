<?php

namespace Database\Factories;

use App\Models\CallOffOrder;
use App\Models\FrameworkAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CallOffOrder> */
class CallOffOrderFactory extends Factory
{
    protected $model = CallOffOrder::class;

    public function definition(): array
    {
        return [
            'framework_agreement_id' => FrameworkAgreement::factory(),
            'purchase_order_id' => null,
            'reference_no' => 'CO-'.fake()->unique()->numerify('########'),
            'ordered_on' => now(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'status' => 'draft',
        ];
    }
}
