<?php

namespace Database\Factories;

use App\Models\ProcurementProject;
use App\Models\ProcurementProjectItem;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProcurementProjectItem> */
class ProcurementProjectItemFactory extends Factory
{
    protected $model = ProcurementProjectItem::class;

    public function definition(): array
    {
        return [
            'procurement_project_id' => ProcurementProject::factory(),
            'procurement_lot_id' => null,
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'approved_budget' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
