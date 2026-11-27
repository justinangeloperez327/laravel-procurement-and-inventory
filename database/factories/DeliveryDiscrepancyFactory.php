<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryDiscrepancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryDiscrepancy> */
class DeliveryDiscrepancyFactory extends Factory
{
    protected $model = DeliveryDiscrepancy::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'delivery_item_id' => null,
            'discrepancy_type' => 'shortage',
            'quantity' => null,
            'description' => fake()->sentence(),
            'status' => 'open',
            'resolution' => null,
            'resolved_at' => null,
        ];
    }
}
