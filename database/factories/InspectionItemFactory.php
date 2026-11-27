<?php

namespace Database\Factories;

use App\Models\DeliveryItem;
use App\Models\Inspection;
use App\Models\InspectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionItem> */
class InspectionItemFactory extends Factory
{
    protected $model = InspectionItem::class;

    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'delivery_item_id' => DeliveryItem::factory(),
            'quantity_accepted' => fake()->numberBetween(0, 10),
            'quantity_rejected' => 0,
            'rejection_reason' => null,
        ];
    }
}
