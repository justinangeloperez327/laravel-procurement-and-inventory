<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequestItem> */
class PurchaseRequestItemFactory extends Factory
{
    protected $model = PurchaseRequestItem::class;

    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'ppmp_item_id' => null,
            'item_id' => null,
            'description' => fake()->words(4, true),
            'specifications' => null,
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'estimated_unit_cost' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
