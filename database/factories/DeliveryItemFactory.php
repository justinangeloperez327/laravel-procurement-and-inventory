<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryItem> */
class DeliveryItemFactory extends Factory
{
    protected $model = DeliveryItem::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'quantity_delivered' => fake()->numberBetween(1, 50),
            'lot_number' => null,
            'expiry_date' => null,
            'serial_numbers' => null,
            'remarks' => null,
        ];
    }
}
